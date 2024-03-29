<?php

namespace Matecat\Dqf\Model\Entity;

abstract class AbstractProject extends BaseApiEntity implements ProjectInterface
{
    /**
     * @var ReviewSettings
     */
    protected $reviewSettings;

    /**
     * @var File[]
     */
    protected $files;

    /**
     * @var array
     */
    protected $targetLanguageAssoc;

    /**
     * @var array
     */
    protected $sourceSegments;

    /**
     * @return ReviewSettings
     */
    public function getReviewSettings()
    {
        return $this->reviewSettings;
    }

    /**
     * @param ReviewSettings $reviewSettings
     */
    public function setReviewSettings($reviewSettings)
    {
        $this->reviewSettings = $reviewSettings;
    }

    /**
     * @param string $name
     *
     * @return File
     */
    public function getFile($name)
    {
        foreach ($this->getFiles() as $f) {
            if ($name === $f->getName()) {
                return $f;
            }
        }
    }

    /**
     * @return File[]
     */
    public function getFiles()
    {
        return $this->files;
    }

    /**
     * @param File $file
     *
     * @return bool
     */
    public function hasFile(File $file)
    {
        if (empty($this->getFiles())) {
            return false;
        }

        foreach ($this->getFiles() as $f) {
            if ($file->getName() === $f->getName()) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param File $file
     */
    public function addFile(File $file)
    {
        if (false === $this->hasFile($file)) {
            $this->files[] = $file;
        }
    }

    /**
     * @param string $languageCode
     * @param File $file
     * @param null $dqfId
     */
    public function assocTargetLanguageToFile($languageCode, File $file, $dqfId = null)
    {
        $fileTargetLang = new FileTargetLang($languageCode, $file);
        if ($dqfId) {
            $fileTargetLang->setDqfId($dqfId);
        }

        $this->targetLanguageAssoc[$languageCode][] = $fileTargetLang;
    }

    /**
     * @param string $languageCode
     * @param string $newLanguageCode
     * @param File $file
     * @param null $dqfId
     */
    public function modifyTargetLanguageToFile($languageCode, $newLanguageCode, File $file, $dqfId = null)
    {
        /** @var FileTargetLang $f */
        foreach ($this->getTargetLanguageAssoc()[$languageCode] as $k => $f) {
            if ($file->getName() === $f->getFile()->getName()) {
                unset($this->targetLanguageAssoc[$languageCode][$k]);

                if (count($this->targetLanguageAssoc[$languageCode]) === 0) {
                    unset($this->targetLanguageAssoc[$languageCode]);
                }
            }
        }

        $fileTargetLang = new FileTargetLang($newLanguageCode, $file);
        if ($dqfId) {
            $fileTargetLang->setDqfId($dqfId);
        }

        $this->targetLanguageAssoc[$newLanguageCode][] = $fileTargetLang;
    }

    /**
     * Clear the targetLanguageAssoc array
     */
    public function clearTargetLanguageAssoc()
    {
        $this->targetLanguageAssoc = [];
    }

    /**
     * @return array
     */
    public function getTargetLanguageAssoc()
    {
        return $this->targetLanguageAssoc;
    }

    /**
     * @param string $languageCode
     *
     * @return bool
     */
    public function hasTargetLanguage($languageCode)
    {
        return isset($this->targetLanguageAssoc[$languageCode]);
    }

    /**
     * @return Language[]
     */
    public function getTargetLanguages()
    {
        $targetLanguages = [];

        foreach (array_keys($this->targetLanguageAssoc) as $targetLanguage) {
            $targetLanguages[]  = new Language($targetLanguage);
        }

        return $targetLanguages;
    }

    /**
     * @return array
     */
    public function getSourceSegments()
    {
        return $this->sourceSegments;
    }

    /**
     * @param File $file
     *
     * @return SourceSegment[]
     */
    public function getSourceSegmentsForAFile(File $file)
    {
        return $this->sourceSegments[$file->getName()];
    }

    /**
     * @param SourceSegment $sourceSegment
     */
    public function addSourceSegment(SourceSegment $sourceSegment)
    {
        if (false === $this->hasSourceSegment($sourceSegment)) {
            $this->sourceSegments[$sourceSegment->getFile()->getName()][] = $sourceSegment;
        }
    }

    /**
     * @param SourceSegment $sourceSegment
     *
     * @return bool
     */
    public function hasSourceSegment(SourceSegment $sourceSegment)
    {
        $fileName = $sourceSegment->getFile()->getName();

        if (empty($this->sourceSegments[$fileName])) {
            return false;
        }

        foreach ($this->sourceSegments[$fileName] as $segment) {
            if ($sourceSegment->isEqualTo($segment)) {
                return true;
            }
        }

        return false;
    }
}
