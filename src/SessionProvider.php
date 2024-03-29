<?php

namespace Matecat\Dqf;

use Matecat\Dqf\Exceptions\SessionProviderException;
use Matecat\Dqf\Model\Entity\DqfUser;
use Matecat\Dqf\Model\Repository\DqfUserRepositoryInterface;
use Matecat\Dqf\Utils\DataEncryptor;

class SessionProvider
{
    /**
     * @var DqfUserRepositoryInterface
     */
    private $dqfUserRepository;

    /**
     * @var Client
     */
    private $client;

    /**
     * @var DataEncryptor
     */
    private $dataEncryptor;

    /**
     * SessionProvider constructor.
     *
     * @param Client                     $client
     * @param DqfUserRepositoryInterface $dqfUserRepository
     */
    public function __construct(Client $client, DqfUserRepositoryInterface $dqfUserRepository)
    {
        $this->client            = $client;
        $this->dqfUserRepository = $dqfUserRepository;
        $this->dataEncryptor     = new DataEncryptor($this->client->getClientParams()['encryptionKey'], $this->client->getClientParams()['encryptionIV']);
    }

    /**
     * @param array $params
     *
     * @return mixed
     * @throws SessionProviderException
     */
    public function create(array $params)
    {
        $this->validate($params);

        $username            = $params['username'];
        $password            = $params['password'];
        $isGeneric           = (isset($params['isGeneric']) and true === $params['isGeneric']) ? true : false;
        $genericEmail        = (isset($params['genericEmail']) and $isGeneric === true) ? $params['genericEmail'] : null;
        $externalReferenceId = (isset($params['externalReferenceId'])) ? $params['externalReferenceId'] : $this->dqfUserRepository->getNextGenericExternalId();

        try {
            $login = $this->client->login(
                [
                    'generic_email' => $genericEmail,
                    'username'      => $username,
                    'password'      => $password,
                ]
            );
        } catch (\Exception $e) {
            throw new SessionProviderException('Login to DQF failed.' . $e->getMessage());
        }

        $dqfUser = new DqfUser();
        $dqfUser->setExternalReferenceId($externalReferenceId);
        $dqfUser->setUsername($this->dataEncryptor->encrypt($username));
        $dqfUser->setPassword($this->dataEncryptor->encrypt($password));
        $dqfUser->setSessionId($login->sessionId);
        $dqfUser->setSessionExpiresAt((int)(strtotime("now") + (int)$login->expires));
        $dqfUser->setIsGeneric($isGeneric);

        if (false === empty($genericEmail) and true === $isGeneric) {
            $dqfUser->setGenericEmail($this->dataEncryptor->encrypt($genericEmail));
        }

        $this->dqfUserRepository->save($dqfUser);

        return $login->sessionId;
    }

    /**
     * @param $params
     *
     * @throws SessionProviderException
     */
    private function validate($params)
    {
        if (false === isset($params['username']) and false === isset($params['password'])) {
            throw new SessionProviderException('Username and password are mandatory');
        }

        if (isset($params['isGeneric']) and true === $params['isGeneric'] and false === isset($params['genericEmail'])) {
            throw new SessionProviderException('genericEmail is mandatary when isGeneric is true');
        }

        if ((false === isset($params['isGeneric']) or true !== $params['isGeneric']) and true === isset($params['genericEmail'])) {
            throw new SessionProviderException('genericEmail must be black if isGeneric is false');
        }
    }

    /**
     * @param string $genericEmail
     *
     * @return mixed
     * @throws SessionProviderException
     */
    public function destroyAnonymous($genericEmail)
    {
        try {
            $dqfUser = $this->dqfUserRepository->getByGenericEmail($this->dataEncryptor->encrypt($genericEmail));

            // At the moment it is not possible to log out anonymously

            return $this->dqfUserRepository->delete($dqfUser);
        } catch (\Exception $e) {
            throw new SessionProviderException('Logout from DQF failed.' . $e->getMessage());
        }
    }

    /**
     * @param string $genericEmail
     * @param null   $externalReferenceId
     *
     * @return mixed
     * @throws SessionProviderException
     */
    public function getByGenericEmail($genericEmail, $externalReferenceId = null)
    {
        if (!$this->hasGenericEmail($genericEmail)) {
            throw new SessionProviderException("Generic user with email " . $genericEmail . " does not exists");
        }

        $dqfUser = $this->dqfUserRepository->getByGenericEmail($this->dataEncryptor->encrypt($genericEmail));
        $dqfUser->setUsername($this->dataEncryptor->decrypt($dqfUser->getUsername()));
        $dqfUser->setPassword($this->dataEncryptor->decrypt($dqfUser->getPassword()));

        if ($dqfUser->isSessionStillValid()) {
            return $dqfUser->getSessionId();
        }

        $params = [
            'genericEmail' => $genericEmail,
            'username'     => $dqfUser->getUsername(),
            'password'     => $dqfUser->getPassword(),
            'isGeneric'    => true,
        ];

        if ($externalReferenceId) {
            $params['externalReferenceId'] = $externalReferenceId;
        }

        return $this->create($params);
    }

    /**
     * @param string $genericEmail
     *
     * @return bool
     */
    public function hasGenericEmail($genericEmail)
    {
        return ($this->dqfUserRepository->getByGenericEmail($this->dataEncryptor->encrypt($genericEmail)) instanceof DqfUser);
    }

    /**
     * @param int $externalReferenceId
     *
     * @return mixed
     * @throws SessionProviderException
     */
    public function destroy($externalReferenceId)
    {
        try {
            $dqfUser = $this->dqfUserRepository->getByExternalId($externalReferenceId);

            if (false === $dqfUser->isSessionStillValid()) {
                $login = $this->client->login(
                    [
                        'generic_email' => (false === empty($dqfUser->getGenericEmail())) ? $this->dataEncryptor->decrypt($dqfUser->getGenericEmail()) : null,
                        'username'      => $this->dataEncryptor->decrypt($dqfUser->getUsername()),
                        'password'      => $this->dataEncryptor->decrypt($dqfUser->getPassword()),
                    ]
                );

                $dqfUser->setSessionId($login->sessionId);
            }

            $this->client->logout(
                [
                    'sessionId' => $dqfUser->getSessionId(),
                    'username'  => $this->dataEncryptor->decrypt($dqfUser->getUsername()),
                ]
            );

            return $this->dqfUserRepository->delete($dqfUser);
        } catch (\Exception $e) {
            throw new SessionProviderException('Logout from DQF failed.' . $e->getMessage());
        }
    }

    /**
     * @param mixed $externalReferenceId
     *
     * @return mixed|void
     * @throws SessionProviderException
     */
    public function getById($externalReferenceId)
    {
        if (false === $this->hasId($externalReferenceId)) {
            throw new SessionProviderException("User with id " . $externalReferenceId . " does not exists");
        }

        $dqfUser = $this->dqfUserRepository->getByExternalId($externalReferenceId);
        $dqfUser->setUsername($this->dataEncryptor->decrypt($dqfUser->getUsername()));
        $dqfUser->setPassword($this->dataEncryptor->decrypt($dqfUser->getPassword()));

        if ($dqfUser->isSessionStillValid()) {
            return $dqfUser->getSessionId();
        }

        return $this->create([
            'externalReferenceId' => $dqfUser->getExternalReferenceId(),
            'username'            => $dqfUser->getUsername(),
            'password'            => $dqfUser->getPassword(),
        ]);
    }

    /**
     * @param mixed $externalReferenceId
     *
     * @return bool
     */
    public function hasId($externalReferenceId)
    {
        return ($this->dqfUserRepository->getByExternalId($externalReferenceId) instanceof DqfUser);
    }

    /**
     * @param string $username
     *
     * @return mixed|void
     * @throws SessionProviderException
     */
    public function getByUsername($username)
    {
        if (false === $this->hasUsername($username)) {
            throw new SessionProviderException("User with username " . $username . " does not exists");
        }

        $dqfUser = $this->dqfUserRepository->getByUsername($this->dataEncryptor->encrypt($username));
        $dqfUser->setUsername($this->dataEncryptor->decrypt($dqfUser->getUsername()));
        $dqfUser->setPassword($this->dataEncryptor->decrypt($dqfUser->getPassword()));

        if ($dqfUser->isSessionStillValid()) {
            return $dqfUser->getSessionId();
        }

        return $this->create([
                'externalReferenceId' => $dqfUser->getExternalReferenceId(),
                'username'            => $dqfUser->getUsername(),
                'password'            => $dqfUser->getPassword(),
        ]);
    }

    /**
     * @param string $username
     *
     * @return bool
     */
    public function hasUsername($username)
    {
        return ($this->dqfUserRepository->getByUsername($this->dataEncryptor->encrypt($username)) instanceof DqfUser);
    }
}
