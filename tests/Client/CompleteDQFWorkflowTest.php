<?php

namespace Matecat\Dqf\Tests\Client;

use Matecat\Dqf\Tests\BaseTest;
use Ramsey\Uuid\Uuid;

class CompleteDQFWorkflowTest extends BaseTest
{
    /**
     * @throws \Exception
     * @test
     */
    public function test_the_complete_workflow()
    {
        /**
         ****************************************************************************
         * create a project
         ****************************************************************************
         */

        // checking if the sourceLanguageCode is valid first
        $languageCode = $this->client->checkLanguageCode([
                'languageCode' => $this->sourceFile[ 'lang' ],
        ]);

        $this->assertEquals('OK', $languageCode->status);

        $masterProjectClientId = Uuid::uuid4()->toString();
        $masterProject         = $this->client->createMasterProject([
                'sessionId'          => $this->sessionId,
                'name'               => 'master-workflow-test',
                'sourceLanguageCode' => $this->sourceFile[ 'lang' ],
                'contentTypeId'      => 1,
                'industryId'         => 2,
                'processId'          => 1,
                'qualityLevelId'     => 1,
                'clientId'           => $masterProjectClientId,
        ]);

        /**
         ****************************************************************************
         * add a template, retrieve and delete it
         ****************************************************************************
         */

        $this->client->addTemplate([
                'sessionId'      => $this->sessionId,
                'name'           => 'test-template-' . Uuid::uuid4()->toString(),
                'contentTypeId'  => rand(1, 15),
                'industryId'     => rand(1, 24),
                'processId'      => rand(1, 4),
                'qualityLevelId' => rand(1, 2),
                'isPublic'       => true,
        ]);

        $templates = $this->client->getTemplates([
                'sessionId' => $this->sessionId,
        ]);

        $this->assertEquals($templates->message, "ProjectTemplates successfully fetched");

        $projectTemplateId = $templates->modelList[ 0 ]->id;

        $getTemplate = $this->client->getTemplate([
                'projectTemplateId' => $projectTemplateId,
                'sessionId'         => $this->sessionId,
        ]);

        $this->assertEquals($getTemplate->message, "ProjectTemplate successfully fetched");

        $deleteTemplate = $this->client->deleteTemplate([
                'projectTemplateId' => $projectTemplateId,
                'sessionId'         => $this->sessionId,
        ]);

        $this->assertEquals($deleteTemplate->message, "Project Template successfully deleted");

        /**
         ****************************************************************************
         * set a file for the project
         ****************************************************************************
         */

        $this->assertNotEmpty($masterProject->dqfId);
        $this->assertNotEmpty($masterProject->dqfUUID);

        $masterProjectFile = $this->client->addMasterProjectFile([
                'sessionId'        => $this->sessionId,
                'projectKey'       => $masterProject->dqfUUID,
                'projectId'        => $masterProject->dqfId,
                'name'             => $this->sourceFile[ 'name' ],
                'numberOfSegments' => count($this->sourceFile[ 'segments' ]),
                'clientId'         => $this->sourceFile[ 'uuid' ],
        ]);

        /**
         ****************************************************************************
         * set target language for the file (checking if the lang is valid first)
         ****************************************************************************
         */

        $languageCode = $this->client->checkLanguageCode([
                'languageCode' => $this->targetFile[ 'lang' ],
        ]);

        $this->assertEquals('OK', $languageCode->status);
        $this->assertNotEmpty($masterProjectFile->dqfId);

        $masterProjectTargetLang = $this->client->addMasterProjectTargetLanguage([
                'sessionId'          => $this->sessionId,
                'projectKey'         => $masterProject->dqfUUID,
                'projectId'          => $masterProject->dqfId,
                'fileId'             => $masterProjectFile->dqfId,
                'targetLanguageCode' => $this->targetFile[ 'lang' ],
        ]);

        /**
         ****************************************************************************
         * set review settings for the project
         ****************************************************************************
         */

        $this->assertNotEmpty($masterProjectTargetLang->dqfId);

        $projectReviewSettings = $this->client->addProjectReviewSettings([
                'sessionId'           => $this->sessionId,
                'projectKey'          => $masterProject->dqfUUID,
                'projectId'           => $masterProject->dqfId,
                'reviewType'          => 'combined',
                'severityWeights'     => '[{"severityId":"1","weight":1}, {"severityId":"2","weight":2}, {"severityId":"3","weight":3}, {"severityId":"4","weight":4}]',
                'errorCategoryIds'    => [1,2,3,4,5],
                'passFailThreshold'   => 1.00,
        ]);

        /**
         ****************************************************************************
         * update source segments in batch
         ****************************************************************************
         */

        $this->assertNotEmpty($projectReviewSettings->dqfId);

        $updatedSourceSegments = $this->client->addSourceSegmentsInBatchToMasterProject([
                'sessionId'  => $this->sessionId,
                'projectKey' => $masterProject->dqfUUID,
                'projectId'  => $masterProject->dqfId,
                'fileId'     => $masterProjectFile->dqfId,
                'body'       => $this->sourceFile[ 'segments' ]
        ]);

        /**
         ****************************************************************************
         * check declared target language
         ****************************************************************************
         */

        $projectFileTargetLang = $this->client->getProjectFileTargetLang([
                'sessionId'  => $this->sessionId,
                'projectKey' => $masterProject->dqfUUID,
                'projectId'  => $masterProject->dqfId,
        ]);

        /**
         ****************************************************************************
         * create a 'translation' child node
         ****************************************************************************
         */

        $this->assertEquals($projectFileTargetLang->message, "Project File/TargetLangs successfully fetched");
        $this->assertEquals($projectFileTargetLang->modelList[0]->file->name, "original-filename");
        $this->assertEquals($projectFileTargetLang->modelList[0]->file->segmentSize, 3);
        $this->assertEquals($updatedSourceSegments->message, "Source Segments successfully created (All segments uploaded)");

        $childTranslation = $this->client->createChildProject([
                'sessionId' => $this->sessionId,
                'parentKey' => $masterProject->dqfUUID,
                'type'      => 'translation',
                'name'      => 'child-workflow-test',
                'isDummy'   => true,
        ]);

        /**
         ****************************************************************************
         * set target language for the child node
         ****************************************************************************
         */

        $this->assertNotEmpty($childTranslation->dqfId);
        $this->assertNotEmpty($childTranslation->dqfUUID);

        $childTranslationTargetLang = $this->client->addChildProjectTargetLanguage([
                'sessionId'          => $this->sessionId,
                'projectKey'         => $childTranslation->dqfUUID,
                'projectId'          => $childTranslation->dqfId,
                'fileId'             => $masterProjectFile->dqfId,
                'targetLanguageCode' => $this->targetFile[ 'lang' ],
        ]);

        /**
         ****************************************************************************
         * update translations in batch
         ****************************************************************************
         */

        $this->assertNotEmpty($childTranslationTargetLang->dqfId);
        $this->assertEquals($childTranslationTargetLang->message, "TargetLang successfully created");

        // update the fake $this->targetFile with real DQL ids for 'sourceSegmentId'
        $segmentPairs = $this->targetFile[ 'segmentPairs' ];
        foreach ($segmentPairs as $key => $segmentPair) {
            $segmentPairs[ $key ][ 'sourceSegmentId' ] = $this->client->getSegmentId([
                    'sessionId' => $this->sessionId,
                    'clientId'  => $this->sourceFile[ 'segments' ][ $key ][ 'clientId' ],
            ])->dqfId;
        }

        $translationsBatch = $this->client->addTranslationsForSourceSegmentsInBatch([
                'sessionId'      => $this->sessionId,
                'projectKey'     => $childTranslation->dqfUUID,
                'projectId'      => $childTranslation->dqfId,
                'fileId'         => $masterProjectFile->dqfId,
                'targetLangCode' => $this->targetFile[ 'lang' ],
                'body'           => $segmentPairs,
        ]);

        $this->assertEquals($translationsBatch->message, "Translations successfully created");

        /**
         ****************************************************************************
         * update a single segment translation
         ****************************************************************************
         */

        $firstSegmentId = $this->client->getSegmentId([
            'sessionId' => $this->sessionId,
            'clientId'  => $this->sourceFile[ 'segments' ][ 0 ][ 'clientId' ],
        ]);

        $firstTranslationId = $this->client->getTranslationId([
            'sessionId' => $this->sessionId,
            'clientId'  => $this->targetFile[ 'segmentPairs' ][ 0 ][ 'clientId' ],
        ]);

        $this->assertNotEmpty($firstSegmentId->dqfId);
        $this->assertNotEmpty($firstTranslationId->dqfId);

        $updateSingleSegmentTranslation = $this->client->updateTranslationForASegment([
            'sessionId'       => $this->sessionId,
            'projectKey'      => $childTranslation->dqfUUID,
            'projectId'       => $childTranslation->dqfId,
            'fileId'          => $masterProjectFile->dqfId,
            'targetLangCode'  => $this->targetFile[ 'lang' ],
            'sourceSegmentId' => $firstSegmentId->dqfId,
            'translationId'   => $firstTranslationId->dqfId,
            'segmentOriginId' => $this->getSegmentOrigin('HT'),
            'targetSegment'   => "The frog in Spain",
            'editedSegment'   => "The frog in Spain (from Barcelona)",
            'time'            => 5435435,
        ]);

        $this->assertEquals($updateSingleSegmentTranslation->message, "Segments successfully updated");

        /**
         ****************************************************************************
         * get the complete translation object previously edited
         ****************************************************************************
         */

        $translationForASegment = $this->client->getTranslationForASegment([
                'sessionId'       => $this->sessionId,
                'projectKey'      => $childTranslation->dqfUUID,
                'projectId'       => $childTranslation->dqfId,
                'fileId'          => $masterProjectFile->dqfId,
                'targetLangCode'  => $this->targetFile[ 'lang' ],
                'sourceSegmentId' => $firstSegmentId->dqfId,
                'translationId'   => $firstTranslationId->dqfId,
        ]);

        $this->assertEquals($translationForASegment->message, "Translation successfully fetched");

        /**
         ****************************************************************************
         * check the status of child node
         ****************************************************************************
         */

        $childNodeStatus = $this->client->getChildProjectStatus([
                'sessionId'  => $this->sessionId,
                'projectKey' => $childTranslation->dqfUUID,
                'projectId'  => $childTranslation->dqfId,
        ]);

        $this->assertEquals($childNodeStatus->status, "OK");
        $this->assertEquals($childNodeStatus->message, "inprogress");

        /**
         ****************************************************************************
         * create a 'review' child node
         ****************************************************************************
         */

        $childReview = $this->client->createChildProject([
                'sessionId' => $this->sessionId,
                'parentKey' => $childTranslation->dqfUUID,
                'type'      => 'review',
                'name'      => 'child-revision-workflow-test',
                'isDummy'   => false, // for type = 'revise' isDummy = false is not allowed
        ]);

        $this->assertNotEmpty($childReview->dqfId);
        $this->assertNotEmpty($childReview->dqfUUID);

        /**
         ****************************************************************************
         * set target language for the child node
         ****************************************************************************
         */

        $childReviewTargetLang = $this->client->addChildProjectTargetLanguage([
                'sessionId'          => $this->sessionId,
                'projectKey'         => $childReview->dqfUUID,
                'projectId'          => $childReview->dqfId,
                'fileId'             => $masterProjectFile->dqfId,
                'targetLanguageCode' => $this->targetFile[ 'lang' ],
        ]);

        $this->assertNotEmpty($childReviewTargetLang->dqfId);
        $this->assertEquals($childReviewTargetLang->message, "TargetLang successfully created");

        /**
         ****************************************************************************
         * set review settings for the child node
         * (this is mandatory for a revision child node, but Can be inherited by master project settings if already declared)
         ****************************************************************************
         */

        $childNodeReviewSettings = $this->client->addProjectReviewSettings([
                'sessionId'           => $this->sessionId,
                'projectKey'          => $childReview->dqfUUID,
                'projectId'           => $childReview->dqfId,
                'reviewType'          => 'combined',
                'severityWeights'     => '[{"severityId":"1","weight":1}, {"severityId":"2","weight":2}, {"severityId":"3","weight":3}, {"severityId":"4","weight":4}]',
                'errorCategoryIds'    => [1,2,3,4,5],
                'passFailThreshold'   => 1.00,
        ]);

        $this->assertNotEmpty($childNodeReviewSettings->dqfId);
        $this->assertEquals($childNodeReviewSettings->message, "Review Settings successfully created");

        /**
         ****************************************************************************
         * add a review template, retrieve and delete it
         ****************************************************************************
         */

        $this->client->addReviewTemplate([
                'sessionId'           => $this->sessionId,
                'projectKey'          => $childReview->dqfUUID,
                'templateName'        => 'test-review-template-' . Uuid::uuid4()->toString(),
                'reviewType'          => 'combined',
                'severityWeights'     => '[{"severityId":"1","weight":1}, {"severityId":"2","weight":2}, {"severityId":"3","weight":3}, {"severityId":"4","weight":4}]',
                'errorCategoryIds'    => [1,2,3],
                'passFailThreshold'   => 1.00,
                'isPublic'            => true,
        ]);

        $templates = $this->client->getReviewTemplates([
                'sessionId' => $this->sessionId,
        ]);

        $this->assertEquals($templates->message, "ReviewTemplates successfully fetched");

        $projectTemplateId = $templates->modelList[ 0 ]->id;

        $getTemplate = $this->client->getReviewTemplate([
                'reviewTemplateId' => $projectTemplateId,
                'sessionId'        => $this->sessionId,
        ]);

        $this->assertEquals($getTemplate->message, "ReviewTemplate successfully fetched");

        $deleteTemplate = $this->client->deleteReviewTemplate([
                'reviewTemplateId' => $projectTemplateId,
                'sessionId'        => $this->sessionId,
        ]);

        $this->assertEquals($deleteTemplate->message, "Review Template successfully deleted");

        /**
         ****************************************************************************
         * check for segmentsId for the file
         ****************************************************************************
         */

        $sourceSegmentIds = $this->client->getSourceSegmentIdsForAFile([
                'sessionId'      => $this->sessionId,
                'projectKey'     => $childReview->dqfUUID,
                'projectId'      => $childReview->dqfId,
                'fileId'         => $masterProjectFile->dqfId,
                'targetLangCode' => $this->targetFile[ 'lang' ],
        ]);

        $this->assertEquals($sourceSegmentIds->message, "Source Segments successfully fetched");
        $this->assertCount(3, $sourceSegmentIds->sourceSegmentList);

        /**
         ****************************************************************************
         * submitting revisions(corrections) in batch
         ****************************************************************************
         */

        $corrections = [];

        // adding a first correction (2 errors)
        $corrections[] = [
                "clientId" => Uuid::uuid4()->toString(),
                "comment"  => "Sample review comment",
                "errors"   => [
                        [
                                "errorCategoryId" => 1,
                                "severityId"      => 2,
                                "charPosStart"    => null,
                                "charPosEnd"      => null,
                                "isRepeated"      => false
                        ],
                        [
                                "errorCategoryId" => 2,
                                "severityId"      => 1,
                                "charPosStart"    => 1,
                                "charPosEnd"      => 5,
                                "isRepeated"      => false
                        ],
                ],
        ];

        // adding a second correction (correction)
        $corrections[] = [
                "clientId"   => Uuid::uuid4()->toString(),
                "comment"    => "Another review comment",
                "correction" => [
                        "content"    => "The frog in Spain (from Barcelona)",
                        "time"       => 10000,
                        "detailList" => [
                                [
                                        "subContent" => "(from Barcelona)",
                                        "type"       => "deleted"
                                ],
                                [
                                        "subContent" => "The frog in Spain  ",
                                        "type"       => "unchanged"
                                ],
                        ]
                ]
        ];

        $batchId = Uuid::uuid4()->toString();

        $updateReviewInBatch = $this->client->updateReviewInBatch([
                'sessionId'      => $this->sessionId,
                'projectKey'     => $childReview->dqfUUID,
                'projectId'      => $childReview->dqfId,
                'fileId'         => $masterProjectFile->dqfId,
                'targetLangCode' => $this->targetFile[ 'lang' ],
                'translationId'  => $firstTranslationId->dqfId,
                'batchId'        => $batchId,
                'overwrite'      => true,
                'body'           => $corrections,
        ]);

        $this->assertEquals($batchId, $updateReviewInBatch->batchId);
        $this->assertEquals("Review successfully created (correction) ", $updateReviewInBatch->message);

        /**
         ****************************************************************************
         * resetting reviews before deleting all the project and child nodes
         * (it's forbidden to delete a child node with reviews)
         ****************************************************************************
         */

        $updateReviewInBatch = $this->client->updateReviewInBatch([
                'sessionId'      => $this->sessionId,
                'projectKey'     => $childReview->dqfUUID,
                'projectId'      => $childReview->dqfId,
                'fileId'         => $masterProjectFile->dqfId,
                'targetLangCode' => $this->targetFile[ 'lang' ],
                'translationId'  => $firstTranslationId->dqfId,
                'batchId'        => $batchId,
                'overwrite'      => true,
                'body'           => [],
        ]);

        $this->assertEquals('OK', $updateReviewInBatch->status);
        $this->assertEquals('All segment revisions cleared', $updateReviewInBatch->message);

        /**
         ****************************************************************************
         * destroy the master and child nodes
         ****************************************************************************
         */

        $deleteChildReview = $this->client->deleteChildProject([
                'sessionId'  => $this->sessionId,
                'projectKey' => $childReview->dqfUUID,
                'projectId'  => $childReview->dqfId,
        ]);

        $this->assertEquals('OK', $deleteChildReview->status);

        $deleteChildProject = $this->client->deleteChildProject([
                'sessionId'  => $this->sessionId,
                'projectKey' => $childTranslation->dqfUUID,
                'projectId'  => $childTranslation->dqfId,
        ]);

        $this->assertEquals('OK', $deleteChildProject->status);

        $deleteMasterProject = $this->client->deleteMasterProject([
                'sessionId'  => $this->sessionId,
                'projectKey' => $masterProject->dqfUUID,
                'projectId'  => $masterProject->dqfId,
        ]);

        $this->assertEquals('OK', $deleteMasterProject->status);
    }

    /**
     * @param $name
     *
     * @return mixed
     */
    private function getSegmentOrigin($name)
    {
        $segmentOrigins = $this->client->getBasicAttributesAggregate([])[ 'segmentOrigin' ];

        foreach ($segmentOrigins as $segmentOrigin) {
            if ($segmentOrigin->name === $name) {
                return $segmentOrigin->id;
            }
        }
    }
}
