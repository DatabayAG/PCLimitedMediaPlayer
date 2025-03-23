/*  Copyright (c) 2017 Institut fuer Lern-Innovation, Friedrich-Alexander-Universitaet Erlangen-Nuernberg */

/**
 * Limited Media Player Page
 * Handles the interactive playing controls and display of playing status
 */
il.PCLimitedMediaPlayerPage = new function () {

  /**
   * Self reference for usage in event handlers
   */
  var self = this;

  /**
   * Page is already initialized
   */
  var initialized = false;

  /**
   * Initialize the page
   * called from ilPCLimitedMediaPlayerPluginGUI::getElementHTML()
   */
  this.initPage = function () {

    if (!initialized) {
      initialized = true;

      $(window).on("message onmessage", self.receivePlayerUpdate);

      $('.limply-play').click(self.playClicked);
      $('.limply-pause').click(self.pauseClicked);
      $('.limply-continue').click(self.continueClicked);
      $('.limply-volume').change(self.volumeChanged);
    }
  };

  this.playClicked = function (event) {
    event.preventDefault();
    var file_id = $(event.currentTarget).attr('data-id');

    $('#limplyModal' + file_id).modal('show');
    self.sendAction(file_id, 'volume', $('#limply' + file_id + ' .limply-volume').val());
    self.sendAction(file_id, 'play');
  };

  this.pauseClicked = function (event) {
    event.preventDefault();
    var file_id = $(event.currentTarget).attr('data-id');
    $('#limplyModal' + file_id).modal('hide');
    self.sendAction(file_id, 'pause');
  };

  this.continueClicked = function (event) {
    event.preventDefault();
    var file_id = $(event.currentTarget).attr('data-id');

    $('#limplyModal' + file_id).modal({ show: true, backdrop: 'static', keyboard: false });
    $('#limplyModal' + file_id + ' button.close').hide();
    self.sendAction(file_id, 'volume', $('#limply' + file_id + ' .limply-volume').val());
    self.sendAction(file_id, 'continue');
  };

  this.volumeChanged = function (event) {
    event.preventDefault();
    var file_id = $(event.currentTarget).attr('data-id');
    self.sendAction(file_id, 'volume', $(event.currentTarget).val());
  };

  /**
   * send an action message to the playing iframe
   * @param file_id
   * @param action
   * @param value
   */
  this.sendAction = function (file_id, action, value = null) {
    var data = {
      action: action,
      value: value
    };

    $('#limply' + file_id + ' iframe').get(0).contentWindow.postMessage(data, '*');
  };

  /**
   * Get a status update from the player frame
   * @param event
   */
  this.receivePlayerUpdate = function (event) {
    var data = event.originalEvent.data;

    $('#limply' + data.file_id + ' .current_plays').html(data.current_plays);
    $('#limply' + data.file_id + ' .current_seconds').html(Math.floor(Math.max(data.current_seconds,
      0)));

    var b_play = $('#limply' + data.file_id + ' .limply-play');
    var b_pause = $('#limply' + data.file_id + ' .limply-pause');
    var b_continue = $('#limply' + data.file_id + ' .limply-continue');
    var d_volume = $('#limply' + data.file_id + ' .limply-volume-div');
    var d_modal = $('#limplyModal' + data.file_id);
    
    switch (data.status) {
      case 'start':
        if (b_play.hasClass('hidden')) {
          $('#limplyModal' + data.file_id).modal('hide');
        }
        b_play.removeClass('hidden');
        b_pause.addClass('hidden');
        b_continue.addClass('hidden');
        break;

      case 'playing':
        b_play.addClass('hidden');
        b_pause.removeClass('hidden');
        b_continue.addClass('hidden');
        break;

      case 'pause':
        b_play.addClass('hidden');
        b_pause.addClass('hidden');
        b_continue.removeClass('hidden');
        break;

      case 'limit':
        b_play.addClass('hidden');
        b_pause.addClass('hidden');
        b_continue.addClass('hidden');
        d_volume.addClass('hidden');
        d_modal.modal('hide');
        break;
    }
  };
};