<?php

namespace Matecat\Dqf\Repository\Api;

use Matecat\Dqf\Model\Entity\ChildProject;
use Matecat\Dqf\Model\Entity\File;
use Matecat\Dqf\Model\Entity\Language;
use Matecat\Dqf\Model\Entity\TranslatedSegment;
use Matecat\Dqf\Model\Repository\TranslationRepositoryInterface;
use Matecat\Dqf\Model\ValueObject\TranslationBatch;
use Ramsey\Uuid\Uuid;

class TranslationRepository extends AbstractApiRepository implements TranslationRepositoryInterface
{
    /**
     * @param TranslationBatch $batch
     *
     * @return mixed|void
     * @throws \Exception
     */
    public function save(TranslationBatch $batch)
    {
        // get the source segments for the child project
        $sourceSegmentIds = $this->client->getSourceSegmentIdsForAFile([
            'sessionId'      => $this->sessionId,
            'projectKey'     => $batch->getChildProject()->getDqfUuid(),
            'projectId'      => $batch->getChildProject()->getDqfId(),
            'fileId'         => $batch->getFile()->getDqfId(),
            'targetLangCode' => $batch->getChildProject()->getTargetLanguages()[ 0 ]->getLocaleCode(),
        ]);

        // build $segmentPairs
        $segmentPairs = [];

        if (false === empty($batch->getSegments())) {
            foreach ($batch->getSegments() as $segment) {
                foreach ($sourceSegmentIds->sourceSegmentList as $index => $item) {
                    if ($item->index === $segment->getSourceSegment()->getIndex()) {
                        $segment->getSourceSegment()->setDqfId($item->dqfId);
                        $segment->getSourceSegment()->setClientId($item->clientId);
                        if (empty($segment->getClientId())) {
                            $segment->setClientId(Uuid::uuid4()->toString());
                        }

                        $segmentPairs[] = [
                            'sourceSegmentId'   => $item->dqfId,
                            'clientId'          => $segment->getClientId(),
                            'targetSegment'     => $segment->getTargetSegment(),
                            'editedSegment'     => $segment->getEditedSegment(),
                            'time'              => $segment->getTime(),
                            'segmentOriginId'   => $segment->getSegmentOriginId(),
                            'mtEngineId'        => $segment->getMtEngineId(),
                            'mtEngineOtherName' => $segment->getMtEngineOtherName(),
                            'matchRate'         => $segment->getMatchRate(),
                            'indexNo'           => (false === empty($segment->getIndexNo())) ? $segment->getIndexNo() : $index
                        ];
                    }
                }
            }
        }

        $translationsBatch = $this->client->addTranslationsForSourceSegmentsInBatch([
            'sessionId'      => $this->sessionId,
            'projectKey'     => $batch->getChildProject()->getDqfUuid(),
            'projectId'      => $batch->getChildProject()->getDqfId(),
            'fileId'         => $batch->getFile()->getDqfId(),
            'targetLangCode' => $batch->getTargetLanguage()->getLocaleCode(),
            'body'           => $segmentPairs,
        ]);

        foreach ($translationsBatch->translations as $i => $translation) {
            $batch->getSegments()[ $i ]->setDqfId($translation->dqfId);
            $this->hydrateLanguage($batch->getSegments()[ $i ]->getTargetLanguage());
        }

        return $batch;
    }

    /**
     * @param ChildProject      $childProject
     * @param File              $file
     * @param TranslatedSegment $translatedSegment
     *
     * @return bool
     */
    public function update(ChildProject $childProject, File $file, TranslatedSegment $translatedSegment)
    {
        if ($this->exists($childProject, $file, $translatedSegment)) {
            $updateSingleSegmentTranslation = $this->client->updateTranslationForASegment([
                'sessionId'           => $this->sessionId,
                'projectKey'          => $childProject->getDqfUuid(),
                'projectId'           => $childProject->getDqfId(),
                'fileId'              => $file->getDqfId(),
                'targetLangCode'      => $translatedSegment->getTargetLanguage()->getLocaleCode(),
                'sourceSegmentId'     => $translatedSegment->getSourceSegment()->getDqfId(),
                'translationId'       => $translatedSegment->getDqfId(),
                'segmentOriginId'     => $translatedSegment->getSegmentOriginId(),
                'targetSegment'       => $translatedSegment->getTargetSegment(),
                'editedSegment'       => $translatedSegment->getEditedSegment(),
                'time'                => $translatedSegment->getTime(),
                'matchRate'           => $translatedSegment->getMatchRate(),
                'mtEngineId'          => $translatedSegment->getMtEngineId(),
                'mtEngineOtherName'   => $translatedSegment->getMtEngineOtherName(),
                'mtEngineVersion'     => $translatedSegment->getMtEngineVersion(),
                'segmentOriginDetail' => $translatedSegment->getSegmentOriginDetail(),
                'clientId'            => $translatedSegment->getClientId(),
            ]);

            return $updateSingleSegmentTranslation->status === 'OK';
        }
    }

    /**
     * @param ChildProject      $childProject
     * @param File              $file
     * @param TranslatedSegment $translatedSegment
     *
     * @return bool
     */
    private function exists(ChildProject $childProject, File $file, TranslatedSegment $translatedSegment)
    {
        $translationForASegment = $this->client->getTranslationForASegment([
            'sessionId'           => $this->sessionId,
            'projectKey'          => $childProject->getDqfUuid(),
            'projectId'           => $childProject->getDqfId(),
            'fileId'              => $file->getDqfId(),
            'targetLangCode'      => $translatedSegment->getTargetLanguage()->getLocaleCode(),
            'sourceSegmentId'     => $translatedSegment->getSourceSegment()->getDqfId(),
            'translationId'       => $translatedSegment->getDqfId(),
        ]);

        return false === empty($translationForASegment->model);
    }
}
