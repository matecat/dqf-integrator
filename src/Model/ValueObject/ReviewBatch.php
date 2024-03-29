<?php

namespace Matecat\Dqf\Model\ValueObject;

use Matecat\Dqf\Model\Entity\BaseApiEntity;
use Matecat\Dqf\Model\Entity\ChildProject;
use Matecat\Dqf\Model\Entity\File;
use Matecat\Dqf\Model\Entity\Language;
use Matecat\Dqf\Model\Entity\Revision;
use Matecat\Dqf\Model\Entity\TranslatedSegment;

class ReviewBatch extends BaseApiEntity
{
    /**
     * @var ChildProject
     */
    private $childProject;

    /**
     * @var File
     */
    private $file;

    /**
     * @var Language
     */
    private $targetLanguage;

    /**
     * @var TranslatedSegment
     */
    private $translation;

    /**
     * @var bool
     */
    private $overwrite;

    /**
     * @var string
     */
    private $batchId;

    /**
     * @var Revision[]
     */
    private $revisions;

    /**
     * ReviewBatch constructor.
     *
     * @param ChildProject      $childProject
     * @param File              $file
     * @param string            $targetLanguageCode
     * @param TranslatedSegment $translation
     * @param string            $batchId
     * @param bool              $overwrite
     */
    public function __construct(ChildProject $childProject, File $file, $targetLanguageCode, TranslatedSegment $translation, $batchId, $overwrite = true)
    {
        $this->childProject   = $childProject;
        $this->file           = $file;
        $this->targetLanguage = new Language($targetLanguageCode);
        $this->translation    = $translation;
        $this->batchId        = $batchId;
        $this->overwrite      = $overwrite;
    }

    /**
     * @return ChildProject
     */
    public function getChildProject()
    {
        return $this->childProject;
    }

    /**
     * @return File
     */
    public function getFile()
    {
        return $this->file;
    }

    /**
     * @return Language
     */
    public function getTargetLanguage()
    {
        return $this->targetLanguage;
    }

    /**
     * @return TranslatedSegment
     */
    public function getTranslation()
    {
        return $this->translation;
    }

    /**
     * @return bool
     */
    public function isOverwrite()
    {
        return $this->overwrite;
    }

    /**
     * @return string
     */
    public function getBatchId()
    {
        return $this->batchId;
    }

    /**
     * @return Revision[]
     */
    public function getRevisions()
    {
        return $this->revisions;
    }

    /**
     * @param Revision $revision
     */
    public function addRevision(Revision $revision)
    {
        $this->revisions[] = $revision;
    }
}
