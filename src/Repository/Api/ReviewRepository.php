<?php

namespace Matecat\Dqf\Repository\Api;

use Matecat\Dqf\Model\Repository\ReviewRepositoryInterface;
use Matecat\Dqf\Model\ValueObject\ReviewBatch;

class ReviewRepository extends AbstractApiRepository implements ReviewRepositoryInterface
{
    /**
     * @param ReviewBatch $batch
     *
     * @return mixed|void
     * @throws \Exception
     */
    public function save(ReviewBatch $batch)
    {
        $reviewedSegments = $batch->getRevisions();
        $corrections = [];
        $errors = [];

        if (false === empty($reviewedSegments)) {
            foreach ($reviewedSegments as $reviewedSegment) {
                // errors
                foreach ($reviewedSegment->getErrors() as $error) {
                    $errors[] = [
                        'errorCategoryId' => $error->getErrorCategoryId(),
                        'severityId'      => $error->getSeverityId(),
                        'charPosStart'    => $error->getCharPosStart(),
                        'charPosEnd'      => $error->getCharPosEnd(),
                        'isRepeated'      => $error->isRepeated()
                    ];
                }

                // detailList
                $detailList = [];
                foreach ($reviewedSegment->getCorrection()->getDetailList() as $correctionItem) {
                    $detailList[] = [
                        'subContent' => $correctionItem->getSubContent(),
                        'type'       => $correctionItem->getType()
                    ];
                }

                // correction
                $correctionArray = [
                    'comment'  => $reviewedSegment->getComment(),
                    'errors'   => $errors,
                    'correction' => [
                        'content'    => $reviewedSegment->getCorrection()->getContent(),
                        'time'       => $reviewedSegment->getCorrection()->getTime(),
                        'detailList' => $detailList
                    ]
                ];

                if (false === empty($reviewedSegment->getClientId())) {
                    $correctionArray['clientId'] = $reviewedSegment->getClientId();
                }

                $corrections[] = $correctionArray;
            }
        }

        $updateReviewInBatch = $this->client->updateReviewInBatch([
            'generic_email'  => $this->genericEmail,
            'sessionId'      => $this->sessionId,
            'projectKey'     => $batch->getChildProject()->getDqfUuid(),
            'projectId'      => $batch->getChildProject()->getDqfId(),
            'fileId'         => $batch->getFile()->getDqfId(),
            'targetLangCode' => $batch->getTargetLanguage()->getLocaleCode(),
            'translationId'  => $batch->getTranslation()->getDqfId(),
            'batchId'        => $batch->getBatchId(),
            'overwrite'      => $batch->isOverwrite(),
            'body'           => $corrections,
        ]);

        if (false === empty($reviewedSegments)) {
            foreach ($reviewedSegments as $key => $reviewedSegment) {
                $reviewedSegment->setDqfId($updateReviewInBatch->createdReviewIds[$key]->reviewContainerId);
                $reviewedSegment->setClientId($updateReviewInBatch->createdReviewIds[$key]->clientId);
            }
        }

        return $batch;
    }
}
