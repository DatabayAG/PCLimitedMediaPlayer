<?php

declare(strict_types=1);

/**
 * @ilCtrl_isCalledBy ilPCLimitedMediaPlayerUploadHandlerGUI: ilPCLimitedMediaPlayerPluginGUI
 */
class ilPCLimitedMediaPlayerUploadHandlerGUI extends ilCtrlAwareStorageUploadHandler
{
    private ?string $purpose;

    public function setPurpose(string $purpose): self
    {
        $this->purpose = $purpose;
        return $this;
    }

    public function getUploadURL(): string
    {
        $this->setParams();
        return $this->ctrl->getLinkTarget($this, self::CMD_UPLOAD);
    }

    public function getExistingFileInfoURL(): string
    {
        $this->setParams();
        return $this->ctrl->getLinkTarget($this, self::CMD_INFO);
    }

    public function getFileRemovalURL(): string
    {
        $this->setParams();
        return $this->ctrl->getLinkTarget($this, self::CMD_REMOVE);
    }

    /**
     * Set parameters expected by the page editor
     * Otherwise a return to parent is called
     * @see ilPageEditorGUI::executeCommand()
     */
    private function setParams()
    {
        $this->ctrl->setParameter($this, 'purpose', $this->purpose);
        $this->ctrl->setParameter($this, 'cname', 'Plugged');
    }

    public function getFileIdentifierParameterName(): string
    {
        return $this->purpose;
    }
}
