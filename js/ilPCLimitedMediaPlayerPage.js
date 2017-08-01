/*  Copyright (c) 2017 Institut fuer Lern-Innovation, Friedrich-Alexander-Universitaet Erlangen-Nuernberg */

/**
 * Limited Media Player Page
 * (anonymous constructor function)
 */
il.PCLimitedMediaPlayerPage = new function() {
	
	/**
	 * Self reference for usage in event handlers
	 * @type object
	 * @private
	 */
	var self = this;

    /**
     * Page is already initialized
     * @type boolean
     * @private
     */
	var initialized = false;


    /**
	 * Texts to be dynamically rendered
	 * @type object
	 * @private
	 */
	var texts = {};


	/**
	 * Initialize the page
	 * called from ilPCLimitedMediaPlayerPluginGUI::getElementHTML(),
	 * @param a_texts	texts to be dynamically rendered
	 */
	this.initPage = function(a_texts) {

	    if (!initialized) {
            initialized = true;
            texts = a_texts;

            $(window).on("message onmessage", self.getUpdate);

            $('.limply-play').click(self.playClicked);
            $('.limply-pause').click(self.pauseClicked);
            $('.limply-continue').click(self.continueClicked);
            $('.limply-volume').change(self.volumeChanged);
        }
    };


	this.playClicked = function(event) {
        event.preventDefault();
        var mob_id = $(event.currentTarget).attr('data-id');

        $('#limplyModal'+mob_id).modal('show');
        self.sendAction(mob_id, 'volume', $('#limply' + mob_id + ' .limply-volume').attr('value'));
        self.sendAction(mob_id, 'play', 0);
    };

	this.pauseClicked = function(event) {
        event.preventDefault();
        var mob_id = $(event.currentTarget).attr('data-id');
        $('#limplyModal'+mob_id).modal('hide');
        self.sendAction(mob_id, 'pause', 0);
    };

	this.continueClicked = function(event) {
        event.preventDefault();
        var mob_id = $(event.currentTarget).attr('data-id');

        $('#limplyModal'+mob_id).modal('show');
        self.sendAction(mob_id, 'volume', $('#limply' + mob_id + ' .limply-volume').attr('value'));
        self.sendAction(mob_id, 'continue', 0);
    };

    this.volumeChanged = function(event) {
        event.preventDefault();
        var mob_id = $(event.currentTarget).attr('data-id');
        self.sendAction(mob_id, 'volume', $(event.currentTarget).attr('value'));
    };

    /**
     * send an action message to the playing iframe
     * @param medium_id
     * @param action
     * @param value
     */
	this.sendAction = function(medium_id, action, value) {
	    var data = {
	        action: action,
	        value: value
	    };

        $('#limply' + medium_id + ' iframe').get(0).contentWindow.postMessage(data, '*');
    };

    /**
     * Get a status update from the player frame
     * @param event
     */
	this.getUpdate = function(event)
    {
        var data = event.originalEvent.data;
        self.updateDisplay(data);
    };

    /**
     * Update the display based on received status update
     * @param data
     */
    this.updateDisplay = function(data)
    {
        $('#limply' + data.mob_id + ' .current_plays').html(data.current_plays);
        $('#limply' + data.mob_id + ' .current_seconds').html(Math.floor(Math.max(data.current_seconds, 0)));

        var b_play = $('#limply' + data.mob_id + ' .limply-play');
        var b_pause =  $('#limply' + data.mob_id + ' .limply-pause');
        var b_continue =  $('#limply' + data.mob_id + ' .limply-continue');
        var d_volume = $('#limply' + data.mob_id + ' .limply-volume-div');
        var d_modal =  $('#limplyModal'+data.mob_id);


        switch(data.status) {
            case 'start':
                if (b_play.hasClass('hidden'))
                {
                    $('#limplyModal'+data.mob_id).modal('hide');
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

    }

};