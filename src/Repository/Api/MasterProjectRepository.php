<?php

namespace Matecat\Dqf\Repository\Api;

use Matecat\Dqf\Cache\BasicAttributes;
use Matecat\Dqf\Constants;
use Matecat\Dqf\Model\Entity\BaseApiEntity;
use Matecat\Dqf\Model\Entity\File;
use Matecat\Dqf\Model\Entity\FileTargetLang;
use Matecat\Dqf\Model\Entity\AbstractProject;
use Matecat\Dqf\Model\Entity\MasterProject;
use Matecat\Dqf\Model\Entity\ReviewSettings;
use Matecat\Dqf\Model\Entity\SourceSegment;
use Matecat\Dqf\Model\Repository\CrudApiRepositoryInterface;
use Matecat\Dqf\Model\ValueObject\Severity;
use Symfony\Component\Config\Definition\Exception\InvalidTypeException;

class MasterProjectRepository extends AbstractProjectRepository implements CrudApiRepositoryInterface
{
    /**
     * Delete a record
     *
     * @param BaseApiEntity $baseEntity
     *
     * @return int
     */
    public function delete(BaseApiEntity $baseEntity)
    {
        /** @var $baseEntity AbstractProject */
        if (false === $baseEntity instanceof AbstractProject) {
            throw new InvalidTypeException('Entity provided is not an instance of MasterProject');
        }

        if (empty($baseEntity->getDqfId())) {
            throw new \DomainException('MasterProject have not a DQF id and cannot be deleted');
        }

        $masterProject = $this->client->deleteMasterProject([
                'generic_email' => $this->genericEmail,
                'sessionId'     => $this->sessionId,
                'projectKey'    => $baseEntity->getDqfUuid(),
                'projectId'     => $baseEntity->getDqfId(),
        ]);

        return ($masterProject->status === 'OK') ? 1 : 0;
    }

    /**
     * Retrieve a record
     *
     * @param int  $dqfId
     * @param null $dqfUuid
     *
     * @return mixed
     */
    public function get($dqfId, $dqfUuid = null)
    {
        // get master project
        $masterProject = $this->client->getMasterProject([
                'generic_email' => $this->genericEmail,
                'sessionId'     => $this->sessionId,
                'projectKey'    => $dqfUuid,
                'projectId'     => $dqfId,
        ]);

        if (false === isset($masterProject->model)) {
            return null;
        }

        $model = $masterProject->model;

        $masterProject = new MasterProject(
            $model->name,
            $model->language->localeCode,
            $model->projectSettings->contentType->id,
            $model->projectSettings->industry->id,
            $model->projectSettings->process->id,
            $model->projectSettings->quality->id
        );

        $masterProject->setDqfId($dqfId);
        $masterProject->setDqfUuid($dqfUuid);

        // set source language
        $this->hydrateSourceLanguage($masterProject);

        // file(s)
        if (false === empty($model->files)) {
            foreach ($model->files as $f) {
                $file = new File($f->name, $f->segmentSize);
                $file->setDqfId($f->id);
                $file->setTmsFileId($f->tmsFile);
                $masterProject->addFile($file);
            }
        }

        // assoc targetLang to file(s)
        if (false === empty($model->fileProjectTargetLangs)) {
            foreach ($model->fileProjectTargetLangs as $assoc) {
                $masterProject->assocTargetLanguageToFile($assoc->projectTargetLang->language->localeCode, $masterProject->getFile($assoc->file->name), $assoc->id);
            }
        }

        // review settings
        $masterProject->setReviewSettings($this->getReviewSettings($dqfId, $dqfUuid));

        return $masterProject;
    }

    /**
     * @param int $dqfId
     * @param string $dqfUuid
     *
     * @return ReviewSettings|mixed
     */
    private function getReviewSettings($dqfId, $dqfUuid)
    {
        $reviewSettings = $this->client->getProjectReviewSettings([
                'generic_email' => $this->genericEmail,
                'sessionId'     => $this->sessionId,
                'projectKey'    => $dqfUuid,
                'projectId'     => $dqfId,
        ]);

        $model = $reviewSettings->model;

        $reviewSettings = new ReviewSettings($model->type);
        $reviewSettings->setDqfId($model->id);
        $reviewSettings->setPassFailThreshold($model->threshold);

        if (false === empty($model->errorSeveritySetting)) {
            foreach ($model->errorSeveritySetting as $setting) {
                $reviewSettings->addSeverityWeight(new Severity($setting->value, $setting->errorSeverity->id));
            }
        }

        foreach ($model->errorTypologySetting as $item) {
            $reviewSettings->addErrorCategoryId($item->errorCategory->id);
        }

        return $reviewSettings;
    }

    /**
     * @param BaseApiEntity $baseEntity
     *
     * @return BaseApiEntity
     * @throws \Exception
     */
    public function save(BaseApiEntity $baseEntity)
    {
        /** @var $baseEntity MasterProject */
        if (false === $baseEntity instanceof MasterProject) {
            throw new InvalidTypeException('Entity provided is not an instance of MasterProject');
        }

        // create master project
        $this->createProject($baseEntity);

        // file(s)
        $this->saveFiles($baseEntity);

        // assoc targetLang to file(s)
        $this->saveTargetLanguageAssoc($baseEntity);

        // review settings
        $this->saveReviewSettings($baseEntity);

        // source segments
        $this->saveSourceSegments($baseEntity);

        return $baseEntity;
    }

    /**
     * @param MasterProject $baseEntity
     */
    private function createProject(MasterProject $baseEntity)
    {
        $masterProject = $this->client->createMasterProject([
                'generic_email'      => $this->genericEmail,
                'sessionId'          => $this->sessionId,
                'name'               => $baseEntity->getName(),
                'sourceLanguageCode' => $baseEntity->getSourceLanguage()->getLocaleCode(),
                'contentTypeId'      => $baseEntity->getContentTypeId(),
                'industryId'         => $baseEntity->getIndustryId(),
                'processId'          => $baseEntity->getProcessId(),
                'qualityLevelId'     => $baseEntity->getQualityLevelId(),
                'clientId'           => $baseEntity->getClientId(),
                'templateName'       => $baseEntity->getTemplateName(),
                'tmsProjectKey'      => $baseEntity->getTmsProjectKey(),
        ]);

        $baseEntity->setDqfId($masterProject->dqfId);
        $baseEntity->setDqfUuid($masterProject->dqfUUID);

        // set source language
        $this->hydrateSourceLanguage($baseEntity);
    }

    /**
     * @param MasterProject $baseEntity
     */
    private function saveFiles(MasterProject $baseEntity)
    {
        if (false === empty($baseEntity->getFiles())) {
            foreach ($baseEntity->getFiles() as $file) {
                $masterProjectFile = $this->client->addMasterProjectFile([
                        'generic_email'    => $this->genericEmail,
                        'sessionId'        => $this->sessionId,
                        'projectKey'       => $baseEntity->getDqfUuid(),
                        'projectId'        => $baseEntity->getDqfId(),
                        'name'             => $file->getName(),
                        'numberOfSegments' => $file->getNumberOfSegments(),
                        'clientId'         => $file->getClientId(),
                ]);

                $file->setDqfId($masterProjectFile->dqfId);
            }
        }
    }

    /**
     * @param MasterProject $baseEntity
     */
    private function saveTargetLanguageAssoc(MasterProject $baseEntity)
    {
        if (false === empty($baseEntity->getTargetLanguageAssoc())) {
            foreach ($baseEntity->getTargetLanguageAssoc() as $targetLanguageCode => $fileTargetLangs) {

                /** @var FileTargetLang $fileTargetLang */
                foreach ($fileTargetLangs as $fileTargetLang) {
                    if (false === empty($fileTargetLang->getFile()->getDqfId())) {
                        $projectTargetLanguageAssoc = $this->client->addMasterProjectTargetLanguage([
                                'generic_email'      => $this->genericEmail,
                                'sessionId'          => $this->sessionId,
                                'projectKey'         => $baseEntity->getDqfUuid(),
                                'projectId'          => $baseEntity->getDqfId(),
                                'fileId'             => $fileTargetLang->getFile()->getDqfId(),
                                'targetLanguageCode' => $targetLanguageCode,
                        ]);

                        $fileTargetLang->setDqfId($projectTargetLanguageAssoc->dqfId);
                        $this->hydrateFileTargetLang($fileTargetLang);
                    }
                }
            }
        }
    }

    /**
     * @param MasterProject $baseEntity
     */
    private function saveReviewSettings(MasterProject $baseEntity)
    {
        if (false === empty($baseEntity->getReviewSettings())) {
            $projectReviewSettings = $this->client->addProjectReviewSettings([
                    'generic_email'       => $this->genericEmail,
                    'sessionId'           => $this->sessionId,
                    'projectKey'          => $baseEntity->getDqfUuid(),
                    'projectId'           => $baseEntity->getDqfId(),
                    'reviewType'          => $baseEntity->getReviewSettings()->getReviewType(),
                    'templateName'        => $baseEntity->getReviewSettings()->getTemplateName(),
                    'severityWeights'     => $baseEntity->getReviewSettings()->getSeverityWeights(),
                    'errorCategoryIds'    => $baseEntity->getReviewSettings()->getErrorCategoryIds(),
                    'passFailThreshold'   => $baseEntity->getReviewSettings()->getPassFailThreshold(),
            ]);

            $baseEntity->getReviewSettings()->setDqfId($projectReviewSettings->dqfId);
        }
    }

    /**
     * Update a record
     *
     * @param BaseApiEntity $baseEntity
     *
     * @return mixed
     */
    public function update(BaseApiEntity $baseEntity)
    {
        /** @var $baseEntity MasterProject */
        if (false === $baseEntity instanceof MasterProject) {
            throw new InvalidTypeException('Entity provided is not an instance of MasterProject');
        }

        // update project
        $masterProject = $this->updateProject($baseEntity);

        // set source language
        $this->hydrateSourceLanguage($baseEntity);

        // file(s)
        $this->updateFiles($baseEntity);

        // assoc targetLang to file(s)
        $this->updateTargetLanguageAssoc($baseEntity);

        // review settings
        $this->updateReviewSettings($baseEntity);

        // source segments
        $this->saveSourceSegments($baseEntity);

        return $masterProject;
    }

    /**
     * @param MasterProject $baseEntity
     *
     * @return mixed
     */
    private function updateProject(MasterProject $baseEntity)
    {
        return $this->client->updateMasterProject([
                'generic_email'      => $this->genericEmail,
                'sessionId'          => $this->sessionId,
                'projectKey'         => $baseEntity->getDqfUuid(),
                'projectId'          => $baseEntity->getDqfId(),
                'name'               => $baseEntity->getName(),
                'sourceLanguageCode' => $baseEntity->getSourceLanguage()->getLocaleCode(),
                'contentTypeId'      => $baseEntity->getContentTypeId(),
                'industryId'         => $baseEntity->getIndustryId(),
                'processId'          => $baseEntity->getProcessId(),
                'qualityLevelId'     => $baseEntity->getQualityLevelId(),
                'clientId'           => $baseEntity->getClientId(),
                'templateName'       => $baseEntity->getTemplateName(),
                'tmsProjectKey'      => $baseEntity->getTmsProjectKey(),
        ]);
    }

    /**
     * @param MasterProject $baseEntity
     */
    private function updateFiles(MasterProject $baseEntity)
    {
        if (false === empty($baseEntity->getFiles())) {
            foreach ($baseEntity->getFiles() as $file) {
                if (false === empty($file->getDqfId())) {
                    $this->client->updateMasterProjectFile([
                            'generic_email'    => $this->genericEmail,
                            'sessionId'        => $this->sessionId,
                            'projectKey'       => $baseEntity->getDqfUuid(),
                            'projectId'        => $baseEntity->getDqfId(),
                            'name'             => $file->getName(),
                            'numberOfSegments' => $file->getNumberOfSegments(),
                            'clientId'         => $file->getClientId(),
                            'fileId'           => $file->getDqfId(),
                    ]);
                } else {
                    $masterProjectFile = $this->client->addMasterProjectFile([
                            'generic_email'    => $this->genericEmail,
                            'sessionId'        => $this->sessionId,
                            'projectKey'       => $baseEntity->getDqfUuid(),
                            'projectId'        => $baseEntity->getDqfId(),
                            'name'             => $file->getName(),
                            'numberOfSegments' => $file->getNumberOfSegments(),
                            'clientId'         => $file->getClientId(),
                    ]);

                    $file->setDqfId($masterProjectFile->dqfId);
                }
            }
        }
    }

    /**
     * @param MasterProject $baseEntity
     */
    private function updateTargetLanguageAssoc(MasterProject $baseEntity)
    {
        if (false === empty($baseEntity->getTargetLanguageAssoc())) {

            // delete ALL target lang assoc
            foreach ($baseEntity->getFiles() as $file) {
                $masterProjectTargetLanguages = $this->client->getMasterProjectTargetLanguages([
                        'generic_email' => $this->genericEmail,
                        'sessionId'     => $this->sessionId,
                        'projectKey'    => $baseEntity->getDqfUuid(),
                        'projectId'     => $baseEntity->getDqfId(),
                        'fileId'        => $file->getDqfId(),
                ]);

                foreach ($masterProjectTargetLanguages->modelList as $masterProjectTargetLanguage) {
                    $this->client->deleteMasterProjectTargetLanguage([
                            'generic_email'  => $this->genericEmail,
                            'sessionId'      => $this->sessionId,
                            'projectKey'     => $baseEntity->getDqfUuid(),
                            'projectId'      => $baseEntity->getDqfId(),
                            'fileId'         => $file->getDqfId(),
                            'targetLangCode' => $masterProjectTargetLanguage->localeCode,
                    ]);
                }
            }

            // And then reset values
            foreach ($baseEntity->getTargetLanguageAssoc() as $targetLanguageCode => $fileTargetLangs) {
                /** @var FileTargetLang $fileTargetLang */
                foreach ($fileTargetLangs as $fileTargetLang) {
                    $projectTargetLanguage = $this->client->addMasterProjectTargetLanguage([
                            'generic_email'      => $this->genericEmail,
                            'sessionId'          => $this->sessionId,
                            'projectKey'         => $baseEntity->getDqfUuid(),
                            'projectId'          => $baseEntity->getDqfId(),
                            'fileId'             => $fileTargetLang->getFile()->getDqfId(),
                            'targetLanguageCode' => $targetLanguageCode,
                    ]);

                    $fileTargetLang->setDqfId($projectTargetLanguage->dqfId);
                    $this->hydrateFileTargetLang($fileTargetLang);
                }
            }
        }
    }

    /**
     * @param MasterProject $baseEntity
     */
    private function updateReviewSettings(MasterProject $baseEntity)
    {
        if (false === empty($baseEntity->getReviewSettings())) {
            if (false === empty($baseEntity->getReviewSettings()->getDqfId())) {
                $this->client->updateProjectReviewSettings([
                        'generic_email'       => $this->genericEmail,
                        'sessionId'           => $this->sessionId,
                        'projectKey'          => $baseEntity->getDqfUuid(),
                        'projectId'           => $baseEntity->getDqfId(),
                        'reviewType'          => $baseEntity->getReviewSettings()->getReviewType(),
                        'severityWeights'     => $baseEntity->getReviewSettings()->getSeverityWeights(),
                        'errorCategoryIds'    => $baseEntity->getReviewSettings()->getErrorCategoryIds(),
                        'passFailThreshold'   => $baseEntity->getReviewSettings()->getPassFailThreshold(),
                ]);
            } else {
                $projectReviewSettings = $this->client->addProjectReviewSettings([
                        'generic_email'       => $this->genericEmail,
                        'sessionId'           => $this->sessionId,
                        'projectKey'          => $baseEntity->getDqfUuid(),
                        'projectId'           => $baseEntity->getDqfId(),
                        'reviewType'          => $baseEntity->getReviewSettings()->getReviewType(),
                        'severityWeights'     => $baseEntity->getReviewSettings()->getSeverityWeights(),
                        'errorCategoryIds'    => $baseEntity->getReviewSettings()->getErrorCategoryIds(),
                        'passFailThreshold'   => $baseEntity->getReviewSettings()->getPassFailThreshold(),
                ]);

                $baseEntity->getReviewSettings()->setDqfId($projectReviewSettings->dqfId);
            }
        }
    }

    /**
     * @param MasterProject $baseEntity
     */
    private function saveSourceSegments(MasterProject $baseEntity)
    {
        if (false === empty($baseEntity->getSourceSegments())) {
            $bodies = [];
            foreach ($baseEntity->getSourceSegments() as $filename => $sourceSegments) {
                $chunks = array_chunk($sourceSegments, Constants::BATCH_LIMIT, true);

                $k = 0;
                for ($i=0; $i < count($chunks);$i++) {
                    $sourceSegments = $chunks[$i];

                    /** @var SourceSegment $sourceSegment */
                    foreach ($sourceSegments as $sourceSegment) {
                        $bodies[$k][ $sourceSegment->getFile()->getDqfId() ][] = [
                                'index'         => $sourceSegment->getIndexNo(),
                                'sourceSegment' => $sourceSegment->getSegment(),
                                'clientId'      => $sourceSegment->getClientId(),
                        ];
                    }

                    $k++;
                }
            }

            // send source segment in batch
            for ($i=0; $i < count($bodies); $i++) {
                foreach ($bodies[$i] as $fileId => $body) {
                    $updatedSourceSegments = $this->client->addSourceSegmentsInBatchToMasterProject([
                            'generic_email' => $this->genericEmail,
                            'sessionId'     => $this->sessionId,
                            'projectKey'    => $baseEntity->getDqfUuid(),
                            'projectId'     => $baseEntity->getDqfId(),
                            'fileId'        => $fileId,
                            'body'          => $body
                    ]);

                    $segmentList = $updatedSourceSegments->segmentList;

                    // hydrate source segment with Dqf Id (comparing with their index)
                    foreach ($baseEntity->getSourceSegments() as $filename => $sourceSegments) {
                        for ($k=0; $k < count($sourceSegments); $k++) {

                            /** @var SourceSegment $sourceSegment */
                            $sourceSegment = $sourceSegments[$k];

                            for ($c=0; $c < count($segmentList); $c++) {
                                if ($sourceSegment->getIndexNo() === $segmentList[$c]->index) {
                                    $sourceSegment->setDqfId($segmentList[ $c ]->dqfId);
                                }
                            }
                        }
                    }
                }
            }
        }
    }

    /**
     * @param MasterProject $masterProject
     */
    private function hydrateSourceLanguage(MasterProject $masterProject)
    {
        if (empty($masterProject->getSourceLanguage())) {
            return;
        }

        foreach (BasicAttributes::get('language') as $language) {
            if ($language->localeCode === $masterProject->getSourceLanguage()->getLocaleCode()) {
                $masterProject->getSourceLanguage()->setName($language->name);
                $masterProject->getSourceLanguage()->setDqfId($language->id);
                break;
            }
        }
    }
}
