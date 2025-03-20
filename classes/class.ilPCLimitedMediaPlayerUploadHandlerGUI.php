<?php

declare(strict_types=1);

/**
 * @ilCtrl_isCalledBy ilPCLimitedMediaPlayerUploadHandlerGUI: ilPCLimitedMediaPlayerPluginGUI
 */
class ilPCLimitedMediaPlayerUploadHandlerGUI extends ilCtrlAwareStorageUploadHandler
{
    public function getUploadURL(): string
    {
        $this->setPageEditorParams();
        return $this->ctrl->getLinkTarget($this, self::CMD_UPLOAD);
    }

    public function getExistingFileInfoURL(): string
    {
        $this->setPageEditorParams();
        return $this->ctrl->getLinkTarget($this, self::CMD_INFO);
    }

    public function getFileRemovalURL(): string
    {
        $this->setPageEditorParams();
        return $this->ctrl->getLinkTarget($this, self::CMD_REMOVE);
    }

    /**
     * Set parameters expected by the page editor
     * Otherwise a return to parent is called
     * @see ilPageEditorGUI::executeCommand()
     */
    private function setPageEditorParams()
    {
        $this->ctrl->setParameter($this, 'cname', 'Plugged');
    }
}
