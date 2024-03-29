<?php

namespace Matecat\Dqf\Model\Entity;

use Matecat\Dqf\Constants;

class ChildProject extends AbstractProject
{
    /**
     * @var string
     */
    private $parentProjectUuid;

    /**
     * @var string
     */
    private $type;

    /**
     * @var string
     */
    private $name;

    /**
     * @var string
     */
    private $assignee;

    /**
     * @var string
     */
    private $assigner;

    /**
     * @var bool
     */
    private $isDummy;

    /**
     * @var int
     */
    private $reviewSettingsId;

    /**
     * ChildProject constructor.
     *
     * @param string $type
     */
    public function __construct($type)
    {
        $this->setType($type);
    }

    /**
     * @return string
     */
    public function getParentProjectUuid()
    {
        return $this->parentProjectUuid;
    }

    /**
     * @param string $parentProjectUuid
     */
    public function setParentProjectUuid($parentProjectUuid)
    {
        $this->parentProjectUuid = $parentProjectUuid;
    }

    /**
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @param string $type
     */
    private function setType($type)
    {
        $allowed = [Constants::PROJECT_TYPE_TRANSLATION, Constants::PROJECT_TYPE_REVIEW];

        if (false === in_array($type, $allowed)) {
            throw new \DomainException($type . 'is not a valid type. [Allowed: '.implode(',', $allowed).']');
        }

        $this->type = $type;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param string $name
     */
    public function setName($name)
    {
        $this->name = $name;
    }

    /**
     * @return string
     */
    public function getAssignee()
    {
        return $this->assignee;
    }

    /**
     * @param string $assignee
     */
    public function setAssignee($assignee)
    {
        $this->assignee = $assignee;
    }

    /**
     * @return string
     */
    public function getAssigner()
    {
        return $this->assigner;
    }

    /**
     * @param string $assigner
     */
    public function setAssigner($assigner)
    {
        $this->assigner = $assigner;
    }

    /**
     * @return bool
     */
    public function isDummy()
    {
        return $this->isDummy;
    }

    /**
     * @param bool $isDummy
     */
    public function setIsDummy($isDummy)
    {
        if (true === $isDummy and $this->type === 'review') {
            throw new \DomainException('\'isDummy\' MUST be set to false if project type is \'review\'');
        }

        $this->isDummy = $isDummy;
    }

    /**
     * @return int
     */
    public function getReviewSettingsId()
    {
        return $this->reviewSettingsId;
    }

    /**
     * @param int $reviewSettingsId
     */
    public function setReviewSettingsId($reviewSettingsId)
    {
        $this->reviewSettingsId = $reviewSettingsId;
    }
}
