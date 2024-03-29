<?php

namespace Matecat\Dqf\Tests\Repository;

use Matecat\Dqf\Constants;
use Matecat\Dqf\Model\Entity\ChildProject;
use Matecat\Dqf\Model\Entity\File;
use Matecat\Dqf\Model\Entity\MasterProject;
use Matecat\Dqf\Model\Entity\Revision;
use Matecat\Dqf\Model\Entity\ReviewSettings;
use Matecat\Dqf\Model\Entity\SourceSegment;
use Matecat\Dqf\Model\Entity\TranslatedSegment;
use Matecat\Dqf\Model\ValueObject\ReviewBatch;
use Matecat\Dqf\Model\ValueObject\RevisionCorrection;
use Matecat\Dqf\Model\ValueObject\RevisionCorrectionItem;
use Matecat\Dqf\Model\ValueObject\RevisionError;
use Matecat\Dqf\Model\ValueObject\Severity;
use Matecat\Dqf\Model\ValueObject\TranslationBatch;
use Matecat\Dqf\Repository\Api\ChildProjectRepository;
use Matecat\Dqf\Repository\Api\MasterProjectRepository;
use Matecat\Dqf\Repository\Api\ReviewRepository;
use Matecat\Dqf\Repository\Api\TranslationRepository;
use Matecat\Dqf\Tests\BaseTest;
use Ramsey\Uuid\Uuid;

class TranslationRepositoryTest extends BaseTest
{
    /**
     * @var MasterProjectRepository
     */
    private $masterProjectRepo;

    /**
     * @var ChildProjectRepository
     */
    private $childProjectRepo;

    /**
     * @var TranslationRepository
     */
    private $translationRepository;

    /**
     * @var ReviewRepository
     */
    private $reviewRepository;

    /**
     * @throws \Matecat\Dqf\Exceptions\SessionProviderException
     */
    protected function setUp()
    {
        parent::setUp();
        $this->masterProjectRepo     = new MasterProjectRepository($this->client, $this->sessionId);
        $this->childProjectRepo      = new ChildProjectRepository($this->client, $this->sessionId);
        $this->translationRepository = new TranslationRepository($this->client, $this->sessionId);
        $this->reviewRepository      = new ReviewRepository($this->client, $this->sessionId);
    }

    /**
     * @test
     * @throws \Exception
     */
    public function save_a_translation_batch()
    {
        // create the master project
        $masterProject = new MasterProject('master-workflow-test', 'it-IT', 1, 2, 3, 1);

        // file(s)
        $file = new File('original-filename', 300);
        $file->setClientId(Uuid::uuid4()->toString());
        $masterProject->addFile($file);

        // assoc targetLang to file(s)
        $masterProject->assocTargetLanguageToFile('en-US', $file);
        $masterProject->assocTargetLanguageToFile('fr-FR', $file);

        // review settings
        $reviewSettings = new ReviewSettings(Constants::REVIEW_TYPE_COMBINED);
        $reviewSettings->addErrorCategoryId(1);
        $reviewSettings->addErrorCategoryId(2);
        $reviewSettings->addErrorCategoryId(3);
        $reviewSettings->addErrorCategoryId(4);
        $reviewSettings->addErrorCategoryId(5);

        $sev1 = new Severity(1, 1);
        $sev2 = new Severity(2, 2);
        $sev3 = new Severity(3, 3);
        $sev4 = new Severity(4, 4);

        $reviewSettings->addSeverityWeight($sev1);
        $reviewSettings->addSeverityWeight($sev2);
        $reviewSettings->addSeverityWeight($sev3);
        $reviewSettings->addSeverityWeight($sev4);

        $reviewSettings->setPassFailThreshold(0.00);
        $masterProject->setReviewSettings($reviewSettings);

        // source segments (300)
        foreach ($this->getSourceSegmentsBatchArray($file) as $sourceSegment) {
            $masterProject->addSourceSegment($sourceSegment);
        }

        // save the master project
        $this->masterProjectRepo->save($masterProject);

        // create the child project
        $childProject = new ChildProject(Constants::PROJECT_TYPE_TRANSLATION);
        $childProject->setParentProjectUuid($masterProject->getDqfUuid());
        $childProject->setName('child-workflow-test');
        $childProject->setIsDummy(true);

        // assoc targetLang to file(s)
        $childProject->assocTargetLanguageToFile('en-US', $masterProject->getFiles()[ 0 ]);
        $childProject->assocTargetLanguageToFile('fr-FR', $masterProject->getFiles()[ 0 ]);

        // review settings
        $reviewSettings = new ReviewSettings(Constants::REVIEW_TYPE_COMBINED);
        $reviewSettings->addErrorCategoryId(1);
        $reviewSettings->addErrorCategoryId(2);
        $reviewSettings->addErrorCategoryId(3);
        $reviewSettings->addErrorCategoryId(4);
        $reviewSettings->addErrorCategoryId(5);

        $sev1 = new Severity(1, 1);
        $sev2 = new Severity(2, 2);
        $sev3 = new Severity(3, 3);
        $sev4 = new Severity(4, 4);

        $reviewSettings->addSeverityWeight($sev1);
        $reviewSettings->addSeverityWeight($sev2);
        $reviewSettings->addSeverityWeight($sev3);
        $reviewSettings->addSeverityWeight($sev4);

        $reviewSettings->setPassFailThreshold(0.00);
        $childProject->setReviewSettings($reviewSettings);

        // save the child project
        $this->childProjectRepo->save($childProject);

        /** @var ChildProject $childProject */
        $childProject = $this->childProjectRepo->get($childProject->getDqfId(), $childProject->getDqfUuid());

        // build the translation batch
        $translationBatch = new TranslationBatch($childProject, $file, 'en-US');

        // submit a 300 translations batch
        foreach ($this->getTargetSegmentsBatchArray($childProject, $file) as $segmTrans) {
            $translationBatch->addSegment($segmTrans);
        }

        // save the translation batch
        $translationBatch = $this->translationRepository->save($translationBatch);

        $this->assertInstanceOf(TranslationBatch::class, $translationBatch);
        $this->assertCount(300, $translationBatch->getSegments());

        /** @var TranslationBatch $translationBatch */
        foreach ($translationBatch->getSegments() as $segment) {
            $this->assertNotNull($segment->getDqfId());
        }

        $firstSegment = $translationBatch->getSegments()[0];

        /** @var SourceSegment $sourceSegment */
        $sourceSegment = $masterProject->getSourceSegments()['original-filename'][0];

        // get a single segment translation
        $getTranslationSegment = $this->translationRepository->getTranslatedSegment(
            $childProject->getDqfId(),
            $childProject->getDqfUuid(),
            $file->getDqfId(),
            'en-US',
            $sourceSegment->getDqfId(),
            $firstSegment->getDqfId()
        );

        $this->assertNotNull($getTranslationSegment->getDqfId());
        $this->assertNotNull($getTranslationSegment->getClientId());
        $this->assertNotNull($getTranslationSegment->getSourceSegmentId());
        $this->assertNotNull($getTranslationSegment->getTargetSegment());
        $this->assertNotNull($getTranslationSegment->getTargetLanguage());

        // update a single segment translation
        $this->update_a_single_segment_translation($translationBatch->getChildProject(), $translationBatch->getFile(), $firstSegment);

        // create a review project and then submit revision(s)
        $this->create_a_review_child_project_and_then_submit_a_revision($translationBatch->getChildProject(), $translationBatch->getFile(), $firstSegment);

        // delete child project
        $deleteChildProject = $this->childProjectRepo->delete($childProject);
        $this->assertEquals(1, $deleteChildProject);

        // delete master project
        $deleteMasterProject = $this->masterProjectRepo->delete($masterProject);
        $this->assertEquals(1, $deleteMasterProject);
    }

    /**
     * @param ChildProject      $childProject
     * @param File              $file
     * @param TranslatedSegment $segment
     */
    public function update_a_single_segment_translation(ChildProject $childProject, File $file, TranslatedSegment $segment)
    {
        $segment->setTargetSegment('The frog in Spain');
        $segment->setEditedSegment('The frog in Spain (from Barcelona)');

        $update = $this->translationRepository->update($childProject, $file, $segment);

        $this->assertTrue($update);
    }

    /**
     * @param ChildProject      $parentChildProject
     * @param File              $file
     * @param TranslatedSegment $segment
     *
     * @throws \Exception
     */
    public function create_a_review_child_project_and_then_submit_a_revision(ChildProject $parentChildProject, File $file, TranslatedSegment $segment)
    {
        $childProject = new ChildProject(Constants::PROJECT_TYPE_REVIEW);
        $childProject->setParentProjectUuid($parentChildProject->getDqfUuid());
        $childProject->setName('Review Job');

        // assoc targetLang to file(s)
        $childProject->assocTargetLanguageToFile('en-US', $file);
        $childProject->assocTargetLanguageToFile('fr-FR', $file);

        try {
            $this->childProjectRepo->save($childProject);
        } catch (\DomainException $e) {
            $this->assertEquals('A \'review\' ChildProject MUST have set review settings', $e->getMessage());
        }

        // review settings
        $reviewSettings = new ReviewSettings(Constants::REVIEW_TYPE_COMBINED);
        $reviewSettings->addErrorCategoryId(1);
        $reviewSettings->addErrorCategoryId(2);
        $reviewSettings->addErrorCategoryId(3);
        $reviewSettings->addErrorCategoryId(4);
        $reviewSettings->addErrorCategoryId(5);

        $sev1 = new Severity(1, 1);
        $sev2 = new Severity(2, 2);
        $sev3 = new Severity(3, 3);
        $sev4 = new Severity(4, 4);

        $reviewSettings->addSeverityWeight($sev1);
        $reviewSettings->addSeverityWeight($sev2);
        $reviewSettings->addSeverityWeight($sev3);
        $reviewSettings->addSeverityWeight($sev4);

        $reviewSettings->setPassFailThreshold(0.00);
        $childProject->setReviewSettings($reviewSettings);

        // save the child project
        /** @var ChildProject $childReview */
        $childReview = $this->childProjectRepo->save($childProject);

        // create a segment review batch
        $correction = new RevisionCorrection('Another review comment', 10000);
        $correction->addItem(new RevisionCorrectionItem('review', 'deleted'));
        $correction->addItem(new RevisionCorrectionItem('Another comment', 'unchanged'));

        $revision = new Revision('this is a comment');
        $revision->addError(new RevisionError(1, 2));
        $revision->addError(new RevisionError(1, 1, 1, 5));
        $revision->setCorrection($correction);
        $revision->setClientId(Uuid::uuid4()->toString());

        $revision2 = new Revision('this is another comment');
        $revision2->addError(new RevisionError(2, 2));
        $revision2->addError(new RevisionError(2, 1, 1, 5));
        $revision2->setCorrection($correction);
        $revision2->setClientId(Uuid::uuid4()->toString());

        $batchId = Uuid::uuid4()->toString();
        $reviewBatch = new ReviewBatch($childReview, $file, 'en-US', $segment, $batchId);
        $reviewBatch->addRevision($revision);
        $reviewBatch->addRevision($revision2);

        $batch = $this->reviewRepository->save($reviewBatch);

        $this->assertInstanceOf(ReviewBatch::class, $batch);

        foreach ($batch->getRevisions() as $revision) {
            $this->assertNotNull($revision->getDqfId());
            $this->assertNotNull($revision->getClientId());
        }

        // resetting reviews before deleting all the project and child nodes
        $emptyReviewBatch = new ReviewBatch($childReview, $file, 'en-US', $segment, $batchId);
        $emptyBatch = $this->reviewRepository->save($emptyReviewBatch);

        $this->assertNull($emptyBatch->getRevisions());

        // deleting the review project
        $delete = $this->childProjectRepo->delete($childReview);

        $this->assertEquals(1, $delete);
    }

    /**
     * @param File $file
     *
     * @return array
     * @throws \Exception
     */
    private function getSourceSegmentsArray(File $file)
    {
        $segments = [];

        foreach ($this->sourceFile[ 'segments' ] as $segment) {
            $sourceSegment = new SourceSegment($file, $segment['index'], $segment['sourceSegment']);
            $sourceSegment->setClientId($segment['clientId']);
            $segments[] = $sourceSegment;
        }

        return $segments;
    }

    /**
     * @param ChildProject $childProject
     * @param File         $file
     *
     * @return TranslatedSegment[]
     * @throws \Exception
     */
    protected function getTargetSegmentsArray(ChildProject $childProject, File $file)
    {
        $translations = [];

        $indexNo = 1;
        foreach ($this->targetFile['segmentPairs'] as $key => $segment) {
            $translations[] = new TranslatedSegment(
                $segment['mtEngineId'],
                $segment['segmentOriginId'],
                $this->targetFile['lang'],
                $this->getSourceSegmentsArray($file)[$key]->getDqfId(),
                $segment['targetSegment'],
                $segment['editedSegment'],
                $indexNo
            );

            $indexNo++;
        }

        return $translations;
    }

    /**
     * @param File $file
     * @param int  $size
     *
     * @return SourceSegment[]
     * @throws \Exception
     */
    private function getSourceSegmentsBatchArray(File $file, $size = 300)
    {
        $segments = [];

        for ($i=1; $i <= $size; $i++) {
            $sourceSegment = new SourceSegment($file, $i, \Faker\Factory::create()->text);
            $sourceSegment->setClientId(Uuid::uuid4()->toString());
            $segments[] = $sourceSegment;
        }

        return $segments;
    }

    /**
     * @param ChildProject $childProject
     * @param File         $file
     * @param int          $size
     *
     * @return TranslatedSegment[]
     * @throws \Exception
     */
    protected function getTargetSegmentsBatchArray(ChildProject $childProject, File $file, $size = 300)
    {
        $translations = [];
        $indexNo = 1;

        for ($i=0; $i < $size; $i++) {
            $sourceSegment = $childProject->getSourceSegments()[$file->getName()][$i];

            $targetSegment = \Faker\Factory::create()->text;
            $editedSegment = \Faker\Factory::create()->text;

            $translatedSegment = new TranslatedSegment(
                22,
                1,
                $this->targetFile['lang'],
                $sourceSegment->getDqfId(),
                $targetSegment,
                $editedSegment,
                $indexNo
            );
            $translatedSegment->setClientId(Uuid::uuid4()->toString());

            $translations[] = $translatedSegment;
            $indexNo++;
        }

        return $translations;
    }
}
