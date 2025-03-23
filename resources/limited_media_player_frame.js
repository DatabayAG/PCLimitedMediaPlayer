/*  Copyright (c) 2017 Institut fuer Lern-Innovation, Friedrich-Alexander-Universitaet Erlangen-Nuernberg */

/**
 * Limited Media Player Frame
 * Handles the player, the playing status and communication with the backend
 */
ilPCLimitedMediaPlayerFrame = new function () {

  /**
   * Self reference for usage in event handlers
   */
  var self = this;

  /**
   * config and status data
   */
  var data = {
    type: 'audio',
    file_id: 0,
    play_with_pause: 0,
    current_plays: 0,
    current_seconds: null,
    status: 'limit',
    volume: 0.5,
    update_url: '',
    volume_url: ''
  };

  /**
   * The player instance
   * @type MediaElementPlayer
   */
  var player;

  /**
   * Interval id for querying the play position
   */
  var timerId;

  /**
   * Update of the playing status after success from the server
   */
  var pendingUpdate = false;

  /**
   * Initialize the page
   * called from ilPCLimitedMediaPlayerGUI::showPlayer(),
   */
  this.initPlayer = function (a_data) {
    data = a_data;

    console.log('initPlayer', data);

    $('#medium').mediaelementplayer({
      features: [],
      success: this.afterInit
    });

    $('#transparent').click(self.preventEvent);
    $('#transparent').contextmenu(self.preventEvent);

    $(window).on("message onmessage", self.getAction);
  };

  /**
   * After initialisation of the player
   * Start playing if pause is not allowed
   * Initialize the event handlers for the player
   */
  this.afterInit = function (media, node, instance) {
    player = media;
    player.setVolume(data.volume);
    player.addEventListener('playing', self.handlePlaying);
    player.addEventListener('pause', self.handlePause);
    player.addEventListener('ended', self.handleEnded);

    if (data.status === 'playing') {
      self.doAction('continue');
    }
  };

  /**
   * Prevent a handling of the fired event
   * Handler for transparent overlay to suppress clicks
   */
  this.preventEvent = function (event) {
    event.preventDefault();
    return false;
  };

  /**
   * Start the progress monitoring
   */
  this.startProgress = function () {
    self.stopProgress(); // stop a former timer
    timerId = window.setInterval(self.handleProgress, 100);
  };

  /**
   * Stop the progress monitoring
   */
  this.stopProgress = function () {
    if (timerId !== null) {
      window.clearInterval(timerId);
    }
  };

  /**
   * Handle the progress timer event
   * This updates the seconds counter and sends the playing time to the server
   */
  this.handleProgress = function () {
    var s1 = Math.floor(data.current_seconds);
    var s2 = Math.floor(player.getCurrentTime());

    // immediately update the display
    data.current_seconds = player.getCurrentTime();
    self.sendUpdateToParentPage();

    // update the status on the server only once a second
    if (s1 !== s2) {
      self.sendStateToBackend();
    }
  };

  /**
   * Handle the 'playing' event of the player (start or continue)
   * This shows the player and updates the controls and data on the server
   */
  this.handlePlaying = function () {
    if (data.type === 'video') {
      $('#startpic').css('visibility', 'hidden');
      $('.mejs__container').css('visibility', 'visible');
    }

    if (data.current_seconds === null) {
      data.current_plays += 1;
      data.current_seconds = 0;
    }

    self.sendUpdateToParentPage();
    self.sendStateToBackend();
    self.startProgress();
  };

  /**
   * Handle 'pause' event of the player
   * This hides the player and updates the controls
   * The data on the server is not updated (a 'pause' status is not explicitly stored)
   */
  this.handlePause = function () {
    $('#startpic').css('visibility', 'visible');
    $('.mejs__container').css('visibility', 'hidden');

    self.stopProgress();

    // the 'pause' status is not explicitly saved on the server but set directly;
    // a pending server update of a former start event should not overwrite this
    pendingUpdate = false;
    data.status = 'pause';
    self.sendUpdateToParentPage();
  };

  /**
   * Handle the 'ended' event of the player
   * This hides the player and updates the controls and data on the server
   */
  this.handleEnded = function () {
    $('#startpic').css('visibility', 'visible');
    $('.mejs__container').css('visibility', 'hidden');
    console.log('handleEnded');

    self.stopProgress();
    data.status = 'pause';
    data.current_seconds = null;
    self.sendUpdateToParentPage();
    self.sendStateToBackend();
  };

  /**
   * Get a playing action from the embedding page
   * @param event
   */
  this.getAction = function (event) {
    var eventdata = event.originalEvent.data;
    self.doAction(eventdata.action, eventdata.value);
  };

  /**
   * Do a playing action
   * @param action
   * @param value
   */
  this.doAction = function (action, value) {
    switch (action) {
      case 'play':
        player.play();
        break;

      case 'pause':
        player.pause();
        break;

      case 'continue':
        // forward to the playing position if needed
        // only forward if current position is at least a second behind the stored one
        var s1 = Math.floor(player.getCurrentTime());
        var s2 = Math.floor(Math.max(data.current_seconds, 0));
        if (s1 < s2) {
          player.setCurrentTime(s2);
        } else {
          data.current_seconds = s1;
        }
        player.play();
        break;

      case 'volume':
        // adjust the volume and send it to the server
        data.volume = value / 100;
        player.setVolume(data.volume);
        self.sendVolumeToBackend();
        break;
    }
  };

  /**
   * Update the display of controls and playing status on the embedding page
   */
  this.sendUpdateToParentPage = function () {
    window.parent.postMessage(data, '*');
  };

  /**
   * Send the current playing state per ajax
   */
  this.sendStateToBackend = function () {
    console.log('sendStateToBackend', data);

    // this may be set to false by the pause handler
    pendingUpdate = true;

    $.ajax({
      type: 'POST',		    // always use POST for the api
      url: data.update_url,	// sync api url
      data: data,			    // data as object
      dataType: 'json',	    // expected response data type
      timeout: 1000		    // 1 second
    })
      .fail(function (jqXHR) {
        console.log('sendStateToBackend failed');
        pendingUpdate = false;
      })
      .done(function (response) {
        console.log('sendStateToBackend done');

        if (pendingUpdate || response.status === 'start' || response.status === 'limit') {
          data.current_plays = response.plays;
          data.current_seconds = response.seconds;
          data.status = response.status;
          self.sendUpdateToParentPage();
        }
        pendingUpdate = false;
      });
  }

  /**
   * Send the volume setting per ajax
   */
  this.sendVolumeToBackend = function () {
    console.log('sendVolumeToBackend', data);

    $.ajax({
      type: 'POST',		    // always use POST for the api
      url: data.volume_url,	// sync api url
      data: data,		        // data as object
      dataType: 'json',	    // expected response data type
      timeout: 1000		    // 1 second
    })
      .fail(function (jqXHR) {
        console.log('sendVolumeToBackend failed');
      })
      .done(function (response) {
        console.log('sendVolumeToBackend done');
      });
  }
};