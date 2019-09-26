<?php

namespace Matecat\Dqf\Commands\Handlers;

use Matecat\Dqf\Commands\CommandHandler;
use Matecat\Dqf\Utils\ParamsValidator;
use Teapot\StatusCode;

class GetMasterProjectFile extends CommandHandler
{
    protected $rules = [
            'sessionId'  => [
                    'required' => true,
                    'type'     => ParamsValidator::DATA_TYPE_STRING,
            ],
            'projectKey' => [
                    'required' => true,
                    'type'     => ParamsValidator::DATA_TYPE_STRING,
            ],
            'projectId'  => [
                    'required' => true,
                    'type'     => ParamsValidator::DATA_TYPE_INTEGER,
            ],
    ];

    /**
     * @param array $params
     *
     * @return mixed|void
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function handle($params = [])
    {
        $response = $this->httpClient->request('GET', $this->buildUri('project/master/{projectId}/file', [ 'projectId' => $params[ 'projectId' ] ]), [
                'headers' => [
                        'sessionId'  => $params[ 'sessionId' ],
                        'projectKey' => $params[ 'projectKey' ],
                        'email'      => isset($params[ 'generic_email' ]) ? $params[ 'generic_email' ] : null,
                ],
        ]);

        if ($response->getStatusCode() === StatusCode::OK) {
            return $this->decodeResponse($response);
        }
    }
}
