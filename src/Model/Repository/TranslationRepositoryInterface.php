<?php

namespace Matecat\Dqf\Model\Repository;

use Matecat\Dqf\Model\Entity\ChildProject;
use Matecat\Dqf\Model\Entity\File;
use Matecat\Dqf\Model\Entity\TranslatedSegment;
use Matecat\Dqf\Model\ValueObject\TranslationBatch;

interface TranslationRepositoryInterface
{
    /**
     * @param int     $childProjectId
     * @param string  $childProjectUuid
     * @param int     $fileId
     * @param string  $targetLanguage
     * @param int     $sourceSegmentDqfId
     * @param int     $segmentTranslationDqfId
     *
     * @return TranslatedSegment
     */
    public function getTranslatedSegment($childProjectId, $childProjectUuid, $fileId, $targetLanguage, $sourceSegmentDqfId, $segmentTranslationDqfId);

    /**
     * @param TranslationBatch $batch
     *
     * @return mixed
     */
    public function save(TranslationBatch $batch);

    /**
     * @param TranslatedSegment $translatedSegment
     *
     * @return bool
     */
    public function update(ChildProject $childProject, File $file, TranslatedSegment $translatedSegment);
}
