/*  Copyright (c) 2017 Institut fuer Lern-Innovation, Friedrich-Alexander-Universitaet Erlangen-Nuernberg */

/**
 * Limited Media Player Frame
 * (anonymous constructor function)
 */
ilPCLimitedMediaPlayerFrame = new function() {
	
	/**
	 * Self reference for usage in event handlers
	 * @type object
	 * @private
	 */
	var self = this;


    /**
     * config and status data
     * @type array
     * @private
     */
    var data = {
        type: 'audio',
        mob_id: 0,
        play_pause: 0,
        current_plays: 0,
        current_seconds: -1,
        status: 'limit',
        volume: 0.5,
        update_url: '',
        volume_url: ''
    };

    /**
     * The player instance
     * @type MediaElementPlayer
     * @private
     */
    var player;

    /**
     * Interval id for querying the play position
     */
    var timerId;

    /**
     * Update of the playing status after success from the server
     * @type {boolean}
     */
    var pendingUpdate = false;

	/**
	 * Initialize the page
	 * called from ilPCLimitedMediaPlayerGUI::showPlayer(),
	 * @param a_data
	 */
	this.initPlayer = function(a_data)
    {
		data = a_data;

        $('#medium').mediaelementplayer({
            features: [],
            success: this.afterInit
        });

        $('#transparent').click( self.preventEvent);
        $('#transparent').contextmenu(self.preventEvent);

        $(window).on("message onmessage", self.getAction);
    };


    /**
     * After initialisation of the play
     * Start playing if pause is not allowed
     * Initialize the event handlers for the player
     * @param media
     * @param node
     * @param instance
     */
    this.afterInit = function(media, node, instance)
    {
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
     * @param event
     * @returns {boolean}
     */
    this.preventEvent = function(event)
    {
        event.preventDefault();
        return false;
    };


    /**
     * Start the progress monitoring
     */
    this.startProgress = function()
    {
        self.stopProgress(); // stop a former timer
        timerId = window.setInterval(self.handleProgress, 100);

    };


    /**
     * Stop the progress monitoring
     */
    this.stopProgress = function()
    {
        if (timerId !== null) {
            window.clearInterval(timerId);
        }
    };

    /**
     * Handle the progress timer event
     * This updates the seconds counter and sends the playing time to the server
     */
    this.handleProgress = function()
    {
        var s1 = Math.floor(data.current_seconds);
        var s2 = Math.floor(player.getCurrentTime());

        // immediatelly update the display
        data.current_seconds = player.getCurrentTime();
        self.updateDisplay();

        // update the status on the server only once a second
        if (s1 !== s2) {
            self.sendState();
        }
    };

    /**
     * Handle the 'playing' event of the player (start or continue)
     * This shows the player and updates the controls and data on the server
     */
    this.handlePlaying = function()
    {
        $('#startpic').css('visibility', 'hidden');
        $('.mejs__container').css('visibility', 'visible');

        if (data.current_seconds < 0)
        {
            data.current_plays += 1;
            data.current_seconds = 0;
        }

        self.updateDisplay();
        self.sendState();
        self.startProgress();
    };

    /**
     * Handle 'pause' event of the player
     * This hides the player and updates the controls
     * The data on the server is not updated (a 'pause' status is not explictly stored)
     */
    this.handlePause = function()
    {
        $('#startpic').css('visibility', 'visible');
        $('.mejs__container').css('visibility', 'hidden');

        self.stopProgress();

        // the 'pause' status is not explictly saved on the server but set dirctly
        // a pending server update of a former start event should not overwrite this
        pendingUpdate = false;
        data.status = 'pause';
        self.updateDisplay();
    };


    /**
     * Handle the 'ended' event of the player
     * This hides the player and updates the controls and data on the server
     */
    this.handleEnded = function()
    {
        $('#startpic').css('visibility', 'visible');
        $('.mejs__container').css('visibility', 'hidden');

        self.stopProgress();
        data.current_seconds = -1;
        self.updateDisplay();
        self.sendState();
    };



    /**
     * Update the display of controls and playing status on the embedding page
     */
    this.updateDisplay = function ()
    {
        window.parent.postMessage(data, '*');
    };


    /**
     * Get a playing action from the embedding page
     * @param event
     */
    this.getAction = function(event)
    {
        var eventdata = event.originalEvent.data;
        self.doAction(eventdata.action, eventdata.value);
    };


    /**
     * Do a playing action
     * @param action
     * @param value
     */
    this.doAction = function(action, value)
    {
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
                }
                else {
                    data.current_seconds = s1;
                }
                player.play();
                break;

            case 'volume':
                // adjust the volume and send it to the server
                data.volume = value/100;
                console.log('volume: ' + data.volume);
                player.setVolume(data.volume);
                self.sendVolume();
                break;

        }
    };


    /**
     * Send the current playing state per ajax
     */
    this.sendState = function()
    {
        console.log('sendState');

        // this may be set to false by the pause handler
        pendingUpdate = true;

        $.ajax({
            type: 'POST',		    // always use POST for the api
            url: data.update_url,	// sync api url
            data: data,			    // data as object
            dataType: 'json',	    // expected response data type
            timeout: 1000		    // 1 second
        })
        .fail(function(jqXHR) {
            console.log('sendState failed');
            pendingUpdate = false;
        })
        .done(function(response) {
            console.log('sendState done');

            if (pendingUpdate || response.status === 'start' || response.status === 'limit') {
                data.current_plays = response.plays;
                data.current_seconds = response.seconds;
                data.status = response.status;
                self.updateDisplay();
            }
            pendingUpdate = false;
        });
    }

    /**
     * Send the volume setting per ajax
     */
    this.sendVolume = function()
    {
        console.log('sendVolume');

        $.ajax({
            type: 'POST',		    // always use POST for the api
            url: data.volume_url,	// sync api url
            data: data,		        // data as object
            dataType: 'json',	    // expected response data type
            timeout: 1000		    // 1 second
        })
            .fail(function(jqXHR) {
                console.log('sendVolume failed');
            })
            .done(function(response) {
                console.log('sendVolume done');
            });
    }


};