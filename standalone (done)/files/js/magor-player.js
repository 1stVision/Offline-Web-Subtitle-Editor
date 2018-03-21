(function(magor) {
	//------------------------------------------------------------------------------

	/**
	 * @class Segment
	 */
	magor.Segment = function(id, startTime, duration, speaker, speakerId, language, confidence, wordTimings, wordTimingsEnd, text) {
		this.id = id;
		this.startTime = startTime;
		this.duration = duration;
		this.speaker = speaker;
		this.speakerId = speakerId;
		this.language = language;
		this.confidence = confidence;
		this.wordTimings = wordTimings;
		this.wordTimingsEnd = wordTimingsEnd;
		this.text = text;
		this.translations = {};
		this.translations[language] = text;
	}

	/**
	 * checks if the segment intersects with a given timestamp.
	 */
	magor.Segment.prototype.intersect = function(time) {
		return this.startTime <= time && this.startTime + this.duration > time;
	}

	// the DOM object of this segment
	magor.Segment.prototype.view = function() {
		if (!this.$_view)
			this.$_view = $('#sid_' + this.id);
		return this.$_view;
	}

	/**
	 * Set/get the highlighting status of the segment.
	 */
	magor.Segment.prototype.highlight = function(value) {
		if (typeof(value) == "boolean") {
			if (value)
				this.view().addClass('highlight');
			else
				this.view().removeClass('highlight');
		} else
			return this.view().hasClass('highlight');
	}

	magor.Segment.prototype.toString = function() {
		return "[Segment " + this.id + "(" + this.startTime + "-" + (this.startTime + this.duration) + " " + this.text + "]";
	}

	//------------------------------------------------------------------------------

	/**
	 * Helper function to parse the raw transcription.
	 */
	magor.parseTranscription = function(transcription) {

		// helper function
		function parseAnnotation(transcription, annotation) {
			for (var i = 0; i < annotation.patterns.length; i++) {
				transcription = transcription.replace(annotation.patterns[i],
					annotation.replace);
			}
			return transcription;
		}

		var annotations = {
			'properNoun': {
				'patterns': [/{&([^}]+)}/g, /&([a-zA-Z\-0-9]+)/g, /&{([^}]+)}/g], // we should have only 1 formal format defined, and pre-process the wrong manual transcription instances instead.
				'replace': function(matched, str) {
					// reference:
					// https://developer.mozilla.org/en-US/docs/Web/JavaScript/Reference/Global_Objects/String/replace
					var words = str.split(' ');
					for (var i = 0; i < words.length; i++) {
						if (words[i])
							words[i] = words[i].charAt(0).toUpperCase() + words[i].slice(1);
					}
					return '<span class="proper-noun">' + words.join(' ') + '</span>';
				}
			},
			'audioEvent': {
				'patterns': [/{([^&}]+)}/g],
				'replace': '<span class="audio-event">($1)</span>',
			},
			'codeSwitch': {
				'patterns': [/\$([a-zA-Z\-0-9]+)/g],
				'replace': '<span class="code-switch">$1</span>',
			},
			'disfluency': {
				'patterns': [/%([a-zA-Z\-0-9]+)/g],
				'replace': '<span class="disfluency">($1)</span>',
			},
		}

		for (var i in annotations) {
			transcription = parseAnnotation(transcription, annotations[i]);
		}
		return transcription.charAt(0).toUpperCase() + transcription.slice(1);;
	}

	//------------------------------------------------------------------------------

	/**
	 * @class MagorPlayer
	 */
	magor.MagorPlayer = function(segments) {
		var self = this;
		this.$_player = $('#player');
		this.$_media = $('.media-box #media');
		this.$_subtitles = $('.media-box .subtitle'); // subtitle container
		this._currentSegments = []; // currently playing segments
		this._segments = []; // list of segments belong to current document.
		this._scrollLock = magor.getParameterByName('scroll_locked').toLowerCase() === 'true' ? true : false; // default to true
		this._duration = 0;
		this._language = magor.getParameterByName('language');
		if (!this._language) this._language = 'EN';
		this._matchedSegmentIds = [];

		// control-box elements
		this.$_timeCurrent = $('#player .time-current');
		this.$_timeDuration = $('#player .time-duration');
		this.$_progressTrack = $('#player .progress-bar .track');
		this.$_progressKnob = $('#player .progress-bar .knob');
		this.$_progressBar = $('#player .progress');
		this.$_progressHighlights = $('#player .highlights');
		this.$_scrollLockBtn = $('#player .scroll-lock');
		this.$_scrollUnlockBtn = $('#player .scroll-unlock');
		this.$_subtitleLanguageBtn = $('#player .subtitle-language');
		this.$_subtitleLanguage = $('#player .subtitle-language-list ul li');
		this.$_subtitleLanguageList = $('#player .subtitle-language-list');

		// initialization
		this.$_subtitles.empty();
		this.$_timeCurrent.text('0:00');
		this.$_timeDuration.text('0:00');
		this._setProgressBar(0);
		this.$_subtitleLanguageBtn.text(this._language);
		this.$_subtitleLanguage.each(function() {
			$(this).removeClass('selected');
			var language = $(this).attr('data-language');
			if (self._language == language)
				$(this).addClass('selected');
		});
		if (this._scrollLock)
			this.$_player.addClass('scroll-locked');
		else
			this.$_player.removeClass('scroll-locked');
		this.$_progressHighlights.empty();

		// buttons
		this.$_prevSegmentButton = $('#player .prev-segment-btn');
		this.$_nextSegmentButton = $('#player .next-segment-btn');
		this.$_prevMatchButton = $('#player .prev-match-btn');
		this.$_nextMatchButton = $('#player .next-match-btn');
		this.$_playButton = $('#player .play-btn');
		this.$_pauseButton = $('#player .pause-btn');
		this.$_replayButton = $('#player .replay-btn');
		this.$_waterMarkButton = $('#player .watermark');

		// sort the segments by startTime, then duration
		segments.sort(function(s1, s2) {
			if (s1.startTime != s2.startTime)
				return s1.startTime < s2.startTime ? -1 : 1;
			else
				return s1.duration < s2.duration ? -1 : 1;
		});
		this._segments = segments;
		//DEBUG
		//console.log(segments);

		// arrange segments into paragraphs
		var paragraphs = [];
		var paragraph = null;
		var curSpeaker = null;
		var counter = 0;
		var contentArray = null;
		this._segmentMap = {};
		for (var i = 0; i < segments.length; i++) {
			this._segmentMap[segments[i].id] = segments[i];
			if (segments[i].speaker != curSpeaker) {
				if (paragraph != null)
					paragraphs.push(paragraph);
				curSpeaker = segments[i].speaker;
				paragraph = '<b class="speaker">' + magor.capitalizeName(curSpeaker) + ' &mdash; </b>';
			}
			contentArray = magor.parseTranscription(segments[i].text).split(" ");
			// added in word timings into each sentence
			paragraph += '<span id="sid_' + segments[i].id + '" data-wordtimings="' + segments[i].wordTimings + '">';
			for (var j = 0; j < contentArray.length; j++) {
				if (segments[i].confidence >= 0.5)
					paragraph += '<span id="sid_' + segments[i].id + '_' + j + '">' + contentArray[j] + '</span>';
				else
					paragraph += '<span class="lowConfidence" id="sid_' + segments[i].id + '_' + j + '">' + contentArray[j] + '</span>';
			}
			paragraph += '</span> ';
		}
		if (paragraph != null)
			paragraphs.push(paragraph);

		// render paragraphs
		this._transcriptions = $('.transcriptions');
		this._transcriptions.empty();
		for (var i = 0; i < paragraphs.length; i++) {
			this._transcriptions.append('<p>' + paragraphs[i] + '</p>');
		}
		this._transcriptions.parent().removeClass('loading');

		// Registering events---------------------------

		// events for segments
		$('.transcriptions').on('click', 'span', function(e) {
			if ($(this).attr('id')) {
				var id = $(this).attr('id').split('_')[1];

				//console.log(id);
				//console.log(self._segmentMap[id]);
				$('#tbxEdit').removeAttr('readonly');
				// enable back button if it is not first element
				if (id == 0) {
					$('#btnPrev').attr('disabled', 'disabled');
					$('#btnNext').removeAttr('disabled');
				} else if (id == segments.length - 1) {
					$('#btnNext').attr('disabled', 'disabled');
					$('#btnPrev').removeAttr('disabled');
				} else {
					$('#btnPrev').removeAttr('disabled');
					$('#btnNext').removeAttr('disabled');
				}

				// put properly into text in textarea
				var arr = [];
				$("#sid_" + id + " span").each(function(index, elem) {
					arr.push($(this).text());
				});
				$('#tbxEdit').val(arr.join(" "));

				self.currentTime(self._segmentMap[id].startTime);

				// setup loop parameters
				loopCounter = parseInt(0);
				loop_start = self._segmentMap[id].startTime;
				wordEndTimes = self._segmentMap[id].wordTimingsEnd.split(",");
				var end_index = intervalNo;
				if(intervalNo > wordEndTimes.length)
					end_index = wordEndTimes.length;
				loop_end = wordEndTimes[end_index-1] * 1000;
				endIndex = intervalNo-1;
				//~ self.currentTime= 95.55;self.togglePlayPause();
				if (self.$_media.get(0).paused) { // pausing
					//self.$_media.get(0).play();
					self.togglePlayPause();
				}

				// update play state
				isPlaying = true;
				playNext = true;
				//$('#tbxEdit').focus();
				e.stopPropagation();
			}
		});

		// events for the player
		this.$_media.bind("timeupdate", function(evt) {
			self._onTimeUpdate(evt);
		});
		//this.$_media.bind('loadedmetadata', function() {
		var t = window.setInterval(function() {
			if (self.$_media.get(0).readyState > 0) {
				clearInterval(t);
				self._onMediaReady();
			}
		}, 100);
		// video load progress: http://stackoverflow.com/a/5182578/564274

		// events for the buttons
		this.$_playButton.click(function() {
			self.togglePlayPause();
			isPlaying = true;
		});
		this.$_pauseButton.click(function() {
			self.togglePlayPause();
			isPlaying = false;
		});
		this.$_replayButton.click(function() {
			self.togglePlayPause();
			isPlaying = false;
			if(this.$_player.hasClass("replay"))
				this.$_player.removeClass("replay");

		});
		this.$_waterMarkButton.click(function() {
			// play the media if its paused
			if (self.$_media.get(0).paused)
				isPlaying = true;
			else
				isPlaying = false;
			self.togglePlayPause();
		});
		this.$_progressBar.click(function(evt) {
			var percent = (evt.clientX - self.$_progressBar.offset().left) /
				self.$_progressBar.width();
			self.currentTime(self._duration * percent);

			// play the media if its paused
			if (self.$_media.get(0).paused)
				self.togglePlayPause();
			isPlaying = true;
			playNext = true;
		});

		this.$_scrollLockBtn.click(function() {
			self.toggleScrollLock();
		});
		this.$_scrollUnlockBtn.click(function() {
			self.toggleScrollLock();
		});
		this.$_subtitleLanguageBtn.click(function() {
			self.$_subtitleLanguageList.toggleClass('popup');
		});
		this.$_subtitleLanguage.click(function() {
			var languageCode = $(this).attr('data-language');
			if (languageCode != self._language) {
				self._language = languageCode;
				$('#search-form input[name=language]').val(languageCode);
				self.$_subtitleLanguage.each(function() {
					$(this).removeClass('selected');
				});
				$(this).addClass('selected');
				self.$_subtitleLanguageBtn.text(languageCode);
				self._updateSubtitles(self._currentSegments, true); // force update subtitles
			}
			// put this here so even clicking the current language in the menu will hide
			// the popup.
			self.$_subtitleLanguageList.removeClass('popup');
		});

		//previous segment
		this.$_prevSegmentButton.click(function() {
			self.prevSegment();
		});

		//next segment
		this.$_nextSegmentButton.click(function() {
			self.nextSegment();
		});

		//previous match
		this.$_prevMatchButton.click(function() {
			self.prevMatch();
		});

		//next match
		this.$_nextMatchButton.click(function() {
			self.nextMatch();
		});

	};

	/** This event is fired once the media file has response from the server.
	 */
	magor.MagorPlayer.prototype._onMediaReady = function() {
		// set time duration text
		this._duration = this.$_media.get(0).duration * 1000;
		this.$_timeDuration.text(this._formatTime(this._duration));
		this._updateProgress();
		this._highlightMatches();
		// jump to the given timestamp
		var time = magor.getParameterByName('time');
		if (time) {
			var sids = this._segmentIdsFromTime(time);
			if (sids.length > 0)
				this._scrollTo(sids[0].id);
			this.currentTime(time);
		}
	}

	/** We must highlight the current playing segment, and translate and display the
	 * transcription if needed.
	 */
	magor.MagorPlayer.prototype._onTimeUpdate = function(evt) {
		this._updateProgress();

		// do not go next segment if user is still editing in textarea
		if(lastWordTime != null && this.currentTime() >= lastWordTime && !playNext && !this.$_media.get(0).paused && !$("#loop").hasClass('on'))
			this.pause();

		// when video has finished playing, push to last frame of video
		if(this.currentTime() >= parseInt(this._duration, 10)-400) {
			this.currentTime(this._duration);
			this.$_player.addClass("replay");
		} else if(this.$_player.hasClass("replay"))
			this.$_player.removeClass("replay");

		// only update the interface when the current playing segments change.
		var newSeg1 = this._segmentIdsFromTime(this.currentTime());
		var curSeg1 = this._currentSegments;
		var correct_new_seg = [];

		// creates two dictionary of the 2 lists
		var newSeg = {},
			curSeg = {};

		for (var i = 0; i < newSeg1.length; i++)
			newSeg[newSeg1[i].id] = newSeg1[i];

		for (var i = 0; i < curSeg1.length; i++)
			curSeg[curSeg1[i].id] = curSeg1[i];

		// the start index to refer to when looping
		var startIndex = 0;
		// text display for highlighting
		var textDisplay = "";

		// highlight the new segments
		for (var id in newSeg) {
			// CASE 1: if it is a new segment
			if (!(id in curSeg)) {
				newSeg[id].highlight(true);

				// update current segment id for reference later when saving
				correct_new_seg = newSeg[id];
				$('#getClickedId').val("sid_" + newSeg[id].id);

				// auto scrolling in transcripts box to current segment
				var scrollAmt = 0;
				var selectedSegment = document.getElementById("sid_" + id);
				if($(".panel-header").height() > 50) {
					if($(window).width() < 992)
						scrollAmt = 180;
					else
						scrollAmt = 195;
				} else
					scrollAmt = 125;

				$('#transcriptsBox').animate({
					scrollTop: selectedSegment.offsetTop - scrollAmt
				}, 100);

				// store all the timings of the words in a segment
				wordStartTimes = newSeg[id].wordTimings.split(",");
				wordEndTimes = newSeg[id].wordTimingsEnd.split(",");

				// set lastWordTime
				lastWordTime = wordEndTimes[wordEndTimes.length-1]*1000;

				// if text area is editable
				if ($("#sid_" + id).length != 0 && !$('#tbxEdit').is('[readonly]')) {
					// put properly into text in textarea
					arr = [];
					var counter = 0;
					var wordHighlight = "^";	// regex to ensure first such phrase only

					// for each word in the span id tag
					$("#sid_" + id + " span").each(function(index, elem) {
						// push it into arr array
						arr.push($(this).text());
						if (counter < intervalNo) {
							wordHighlight += $(this).text() + " ";
							counter++;
						}
					});
					$('#tbxEdit').val(arr.join(" "));
					// escape full stops from regex search
					wordHighlight = wordHighlight.replace(/\./g, '\\\.');

					if (wordHighlight.trim().length > 0) {
						$('#tbxEdit').highlightTextarea('destroy');
						wordsToBeHighlighted = [];
						wordsToBeHighlighted.push(wordHighlight.trim());
						$('#tbxEdit').highlightTextarea({
							words: wordsToBeHighlighted,
							caseSensitive: false
						});
					}
				}
				// set loop start to segment start time, loop end to end of region
				loop_start = newSeg[id].startTime;
				endIndex = intervalNo-1;	// start from 0
				// prevent overflow
				if(endIndex >= wordEndTimes.length)
					endIndex = wordEndTimes.length-1;

				loop_end = (wordEndTimes[endIndex] * 1000);

				// remove any temporary highlight in case 2
				if(temp_hightlight != "" && $("#" + temp_hightlight).hasClass("highlight")) {
					$("#" + temp_hightlight).removeClass("highlight")
					temp_hightlight = "";
				}

				// always set caret to end of first word just for user to edit word conveniently
				if (arr != null && !$('#tbxEdit').is('[readonly]')) {
					$("#tbxEdit").setCaretPos(arr[0].length+1);
					caretStart = arr[0].length;
					caretEnd = arr[0].length;
				}
				break;
			}
			// CASE 2: still on existing segment
			else {
				// highlight segment if it lost the highlight
				if(!$("#" + $("#getClickedId").val()).hasClass("highlight")) {
					temp_hightlight = $("#getClickedId").val();
					$("#" + $("#getClickedId").val()).addClass("highlight");
				}

				// do processing first to check if caret position has JUST been updated
				// if so, then we need to update the region to highlight accordingly
				var caretUpdated = false;
				if ($("#caretUpdated").val() == "true") {
					// max length of text we want to retrieve
					var counter = caretStart+1;

					// loop through current text to check the length
					// smaller than or equals to in order to capture caret positioned on last text
					for (var i = 0; i <= arr.length; i++) {
						if (counter > 0) {
							// add 1 for whitespace between words
							if(arr[i] != null)
								counter -= arr[i].length + 1;
						} else {
							// minus 1 here since we already reached our aim but the for loop still goes 1 more time to check the condition
							startIndex = i - 1;
							caretUpdated = true;
							break;
						}
					}
					$("#caretUpdated").val("false");
					// prevent underflow
					if(startIndex < 0)
						startIndex = 0;
					//console.log(arr);
					//console.log("New index at " + startIndex);

					// if it is a natural shift (no clicking/typing) to next region, we need to update startIndex
					endIndex = startIndex + (intervalNo-1);	// start from 0
					// prevent array overflow
					if (endIndex >= wordEndTimes.length)
						endIndex = wordEndTimes.length-1;

					$('#tbxEdit').highlightTextarea('destroy');

					for (var i = startIndex; i <= endIndex; i++)
						textDisplay += arr[i] + " ";
					// escape full stops from regex search
					textDisplay = textDisplay.replace(/\./g, '\\\.');

					wordsToBeHighlighted = [];
					wordsToBeHighlighted.push(textDisplay.trim());

					$('#tbxEdit').highlightTextarea({
						words: wordsToBeHighlighted,
						caseSensitive: false
					});
					// set the caret back to where it was
					if(caretStart == caretEnd)
						$("#tbxEdit").setCaretPos(caretStart+1);
					else
						$("#tbxEdit").setSelection(caretStart, caretEnd);
				}

				// if loop is turned on
				if ($("#loop").hasClass('on')) {
					// if player is not paused, we keep playing the same region until we hit the loop cycle treshold
					// or we enter to refresh the loop and highlighting when caret is updated
					if (!this.$_media.get(0).paused && this.currentTime() >= loop_end) {
						// keep repeating if loop cycles not reached yet
						if (loopCounter < $("#loopCycle").val()) {
							this.currentTime(loop_start);
							this.$_media.get(0).play;
							loopCounter++;
						} else {
							// reset text highlighting and loop counter
							textDisplay = "";
							loopCounter = parseInt(0);

							// end of segment, so we go next segment
							if(endIndex == wordEndTimes.length-1 && !caretUpdated) {
								goNextSegment();
							} else {
								// if it is a natural shift (no clicking/typing) to next region, we need to update startIndex
								if(!caretUpdated)
									startIndex = endIndex+1;
								endIndex = startIndex + (intervalNo-1);	// start from 0
								// prevent array overflow
								if (endIndex >= wordEndTimes.length)
									endIndex = wordEndTimes.length-1;
								//console.log("Start: " + startIndex + " | End: " + endIndex);

								$('#tbxEdit').highlightTextarea('destroy');

								for (var i = startIndex; i <= endIndex; i++)
									textDisplay += arr[i] + " ";
								// escape full stops from regex search
								textDisplay = textDisplay.replace(/\./g, '\\\.');

								wordsToBeHighlighted = [];
								wordsToBeHighlighted.push(textDisplay.trim());

								$('#tbxEdit').highlightTextarea({
									words: wordsToBeHighlighted,
									caseSensitive: false
								});
								// set the caret back to where it was
								if(caretStart == caretEnd)
									$("#tbxEdit").setCaretPos(caretStart+1);
								else
									$("#tbxEdit").setSelection(caretStart, caretEnd);

								// set loop start to start of region, loop end to end of region
								loop_start = (wordStartTimes[startIndex] * 1000);
								loop_end = (wordEndTimes[endIndex] * 1000);
								// special cases
								if (loop_start > loop_end) {
									var temp = loop_start;
									loop_start = loop_end;
									loop_end = temp;
								}
							}
						}
					}
				}
				// when player timing reaches the next region, we highlight the next region words accordingly
				// OR when the caret (cursor) position is updated. otherwise we don't update at all
				else {
					if(this.currentTime()/1000 >= wordEndTimes[endIndex] || caretUpdated) {
						// reset text highlighting and loop counter
						textDisplay = "";
						loopCounter = parseInt(0);

						// if it is a natural shift (no clicking/typing) to next region, we need to update startIndex
						if(!caretUpdated)
							startIndex = endIndex+1;
						endIndex = startIndex + (intervalNo-1);	// start from 0
						// prevent array overflow
						if (endIndex >= wordEndTimes.length)
							endIndex = wordEndTimes.length-1;

						$('#tbxEdit').highlightTextarea('destroy');

						for (var i = startIndex; i <= endIndex; i++)
							textDisplay += arr[i] + " ";
						// escape full stops from regex search
						textDisplay = textDisplay.replace(/\./g, '\\\.');

						wordsToBeHighlighted = [];
						wordsToBeHighlighted.push(textDisplay.trim());

						$('#tbxEdit').highlightTextarea({
							words: wordsToBeHighlighted,
							caseSensitive: false
						});
						// set the caret back to where it was
						if(caretStart == caretEnd)
							$("#tbxEdit").setCaretPos(caretStart+1);
						else
							$("#tbxEdit").setSelection(caretStart, caretEnd);

						// set loop start to start of region, loop end to end of region
						loop_start = (wordStartTimes[startIndex] * 1000);
						loop_end = (wordEndTimes[endIndex] * 1000);
						// special cases
						if (loop_start > loop_end) {
							var temp = loop_start;
							loop_start = loop_end;
							loop_end = temp;
						}
					}
				}
			}
		}

		// unhighlight the old segments
		for (var id in curSeg) {
			if (!(id in newSeg)) {
				curSeg[id].highlight(false);
				break;
			}
		}
		this._updateSubtitles(newSeg1);
		this._currentSegments = newSeg1;

		if (this._scrollLock && newSeg1.length > 0)
			this._scrollTo(newSeg1[0].id);
	}

	/**
	 * Gets the list of segment IDs whose time spans intersect with the given
	 * timestamp. Note that this can returns more than 1 IDs, for example in
	 * overlapping speech.
	 *
	 * @return: an array of segments having startTime<=time and end time >= time
	 */
	magor.MagorPlayer.prototype._segmentIdsFromTime = function(time) {
		// just do a binary search
		var low = 0,
			high = this._segments.length - 1,
			mid;
		while (low <= high) {
			mid = Math.floor((low + high) / 2);
			if (this._segments[mid].startTime + this._segments[mid].duration < time)
				low = mid + 1;
			else if (this._segments[mid].startTime > time)
				high = mid - 1;
			else // stop finding when this._segments[mid].intersect(time)
				break;
		}
		// now check all the adjacent segments and add to an array in increasing time
		// order.
		var results = [],
			i = mid;
		for (var i = mid - 5; i <= mid + 5; i++) {
			if (i >= 0 && i < this._segments.length && this._segments[i].intersect(time))
				results.push(this._segments[i]);
		}

		return results;
	}

	/** Format a timestamp into '1:02:03', '2:03', '0:04' formats
	 */
	magor.MagorPlayer.prototype._formatTime = function(millisec) {
		var secs = millisec / 1000;
		var hours = Math.floor(secs / 3600);
		secs -= hours * 3600;
		var mins = Math.floor(secs / 60);
		secs -= mins * 60;
		secs = Math.floor(secs);
		if (hours > 0 && mins < 10)
			mins = '0' + mins;
		if (secs < 10)
			secs = '0' + secs;
		if (hours > 0)
			return hours + ':' + mins + ':' + secs;
		else
			return mins + ':' + secs;
	}

	magor.MagorPlayer.prototype._setProgressBar = function(millisec) {
		this.$_progressTrack.css({
			width: (millisec / this._duration) * 100 + '%'
		});
		this.$_progressKnob.css({
			left: (millisec / this._duration) * 100 + '%'
		});
	}

	/** Update GUI about the playing progress
	 */
	magor.MagorPlayer.prototype._updateProgress = function() {
		this.$_timeCurrent.text(this._formatTime(this.currentTime()));
		//console.log(this.currentTime());
		this._setProgressBar(this.currentTime());
	}

	magor.MagorPlayer.prototype._updateSubtitles = function(segments, forceUpdate) {
		var self = this;
		// check if subtitles is the same as the currently displayed subtitles.
		// if so, we don't have to update the container.
		if (segments.toString() !== this._currentSegments.toString() || forceUpdate) {
			this.$_subtitles.empty();
			for (var i = 0; i < segments.length; i++) {
				var s = segments[i];
				this.$_subtitles.append('<p id="subtitle_' + s.id + '"></p>');
				if (s.language != this._language && !(this._language in this._segmentMap[s.id].translations)) {
					var callback = function(sid, translation) {
						//add new translation to segmentMap
						self._segmentMap[sid].translations[self._language] = translation;
						$('#subtitle_' + sid).html(magor.parseTranscription(self._segmentMap[sid].translations[self._language]));
					};
					//~ this.translate(s.text, s.language, this._language, s.id, callback);
				} else {
					if (this._segmentMap[s.id].translations[this._language]) {
						$('#subtitle_' + s.id).html(magor.parseTranscription(self._segmentMap[s.id].translations[this._language]));
					}
				}
			}
		}
	}

	magor.MagorPlayer.prototype._highlightMatches = function() {
		this.$_progressHighlights.empty();
		//console.log(this._matchedSegmentIds.length);
		for (var i = 0; i < this._matchedSegmentIds.length; i++) {
			var segment = this._segmentMap[this._matchedSegmentIds[i]];
			// highlight the transcription
			//console.log(segment);
			segment.view().addClass('matched');
			// now highlight the progress bar
			var $highlight = $('<div></div>');
			$highlight.addClass('highlight');
			$highlight.css({
				left: (segment.startTime / this._duration * 100) + '%',
				width: (segment.duration / this._duration * 100) + '%'
			});
			//console.log($highlight);
			this.$_progressHighlights.append($highlight);
		}
	}

	magor.MagorPlayer.prototype.highlightMatches = function(sids) {
		this._matchedSegmentIds = sids;
		//console.log(this._matchedSegmentIds);
		if (this._duration > 0) // the media is ready
			this._highlightMatches();
	}

	//   Newly added to unhighlight old matches
	magor.MagorPlayer.prototype._unhighlightMatches = function() {
		this.$_progressHighlights.empty();
		//console.log(this._unmatchedSegmentIds.length);
		for (var i = 0; i < this._unmatchedSegmentIds.length; i++) {
			var segment = this._segmentMap[this._unmatchedSegmentIds[i]];
			// unhighlight the transcription
			segment.view().removeClass('matched');
		}
	}

	magor.MagorPlayer.prototype.unhighlightMatches = function(sids) {
		this._unmatchedSegmentIds = sids;
		//console.log(this._matchedSegmentIds);
		if (this._duration > 0) // the media is ready
			this._unhighlightMatches();
	}


	magor.MagorPlayer.prototype._scrollTo = function(sid) {
		var currentScroll = $(document).scrollTop();
		var $segment = $('#sid_' + sid);
		var segmentTop = $segment.offset().top;
		var segmentHeight = $segment.height();
		var windowHeight = $(window).height();

		if (currentScroll > segmentTop ||
			currentScroll + windowHeight < segmentTop + segmentHeight) {
			$('html, body').animate({
				scrollTop: segmentTop - windowHeight / 2
			}, 800);
		}
	}

	/** Set/get current time. Time of the player is in seconds, while time that we
	 * deal with is milliseconds.
	 */
	magor.MagorPlayer.prototype.currentTime = function(millisec) {
		if (millisec)
			this.$_media.get(0).currentTime = millisec / 1000;
		else
			return Math.round(this.$_media.get(0).currentTime * 1000);
	}

	magor.MagorPlayer.prototype.togglePlayPause = function() {
		var media = this.$_media.get(0);
		var player = this.$_player;
		if(player.hasClass("replay"))
			player.attr("class", "");
		setTimeout(function () {
			// Resume play if the element if is paused
			if (media.paused) {
				player.removeClass("paused");
				media.play();
			}
			// Pause if the element if is playing
			else {
				player.addClass("paused");
				media.pause();
			}
		}, 150);
	}

	/**
	 * get lastest segment which is being play
	 */
	magor.MagorPlayer.prototype._getSingleCurrentSeg = function() {
		var sids = this._segmentIdsFromTime(this.currentTime());
		var curSeg;
		for (var i = 0; i < sids.length; i++) {
			if (!curSeg || (curSeg.startTime < sids[i].startTime)) {
				curSeg = sids[i];
			}
		}
		return curSeg;
	}

	/**
	 * jump to next segment
	 *
	 */
	magor.MagorPlayer.prototype.nextSegment = function() {
		var curSeg = this._getSingleCurrentSeg();
		var curSegIndex = this._segments.indexOf(curSeg);
		if (curSegIndex >= 0 && curSegIndex < this._segments.length) {
			var nextSegment = this._segments[curSegIndex++];
			while (curSeg.startTime >= nextSegment.startTime && curSegIndex < this._segments.length) {
				nextSegment = this._segments[curSegIndex++];
			}
			this.currentTime(nextSegment.startTime);
			// setup loop parameters
			loopCounter = parseInt(0);
			loop_start = nextSegment.startTime;
			wordEndTimes = nextSegment.wordTimingsEnd.split(",");
			var end_index = intervalNo;
			if(intervalNo > wordEndTimes.length)
				end_index = wordEndTimes.length;
			loop_end = wordEndTimes[end_index-1] * 1000;
			endIndex = intervalNo-1;
		}
	}

	/**
	 * jump to previous segment
	 */
	magor.MagorPlayer.prototype.prevSegment = function() {
		var curSeg = this._getSingleCurrentSeg();
		var curSegIndex = this._segments.indexOf(curSeg);
		if (curSegIndex >= 0 && curSegIndex <= this._segments.length) {
			var prevSegment = this._segments[curSegIndex--];
			while (curSeg.startTime <= prevSegment.startTime && curSegIndex >= 0) {
				prevSegment = this._segments[curSegIndex--];
			}
			this.currentTime(prevSegment.startTime);
			// setup loop parameters
			loopCounter = parseInt(0);
			loop_start = prevSegment.startTime;
			wordEndTimes = prevSegment.wordTimingsEnd.split(",");
			var end_index = intervalNo;
			if(intervalNo > wordEndTimes.length)
				end_index = wordEndTimes.length;
			loop_end = wordEndTimes[end_index-1] * 1000;
			endIndex = intervalNo-1;
		}
	}
	/**
	 * jump to next match
	 */
	magor.MagorPlayer.prototype.nextMatch = function() {
		var nextMatchedSeg;
		var curSegment = this._getSingleCurrentSeg();
		for (var i = 0; i < this._matchedSegmentIds.length; i++) {
			var s = this._segmentMap[this._matchedSegmentIds[i]];
			if (s.startTime > curSegment.startTime && (!nextMatchedSeg || nextMatchedSeg.startTime > s.startTime)) {
				nextMatchedSeg = s;
			}
		}
		if (nextMatchedSeg) {
			this.currentTime(nextMatchedSeg.startTime);
		}
	}

	/**
	 * jump to previous match
	 */
	magor.MagorPlayer.prototype.prevMatch = function() {
		var prevMatchedSeg;
		var curSegment = this._getSingleCurrentSeg();
		for (var i = 0; i < this._matchedSegmentIds.length; i++) {
			var s = this._segmentMap[this._matchedSegmentIds[i]];
			if (s.startTime < curSegment.startTime && (!prevMatchedSeg || prevMatchedSeg.startTime < s.startTime)) {
				prevMatchedSeg = s;
			}
		}
		if (prevMatchedSeg) {
			this.currentTime(prevMatchedSeg.startTime);
		}
	}

	magor.MagorPlayer.prototype.toggleScrollLock = function() {
		this._scrollLock = !this._scrollLock;
		if (this._scrollLock) {
			this.$_player.addClass('scroll-locked');
		} else {
			this.$_player.removeClass('scroll-locked');
		}
		$('#search-form input[name=scroll_locked]').val(this._scrollLock);
	}

	/* Strips all annotations (in preparation for sending to Google Translate).
	 */
	//~ magor.MagorPlayer.prototype._stripAnnotations = function(text) {
	//~ text = magor.parseTranscription(text);
	//~ text = text.replace(/<[^>]*>/g, '');
	//~ text = text.replace(/\((breath|lipsmack|clapping[^)]*|ah|uh|eh|er|um|lah|meh|lor|hah|oh|)\)/g, "");
	//~ text = text.replace('&amp;', '');
	//~ text = text.replace('$', '');
	//~ text = text.replace('~', '');
	//~ text = text.replace('.', '');
	//~ return text;
	//~ }

	//~ magor.MagorPlayer.prototype.translate = function(text, sourceLanguage, targetLanguage, segmentId, callback, context) {
	//~ text = this._stripAnnotations(text);
	//~ if (text.trim()) {
	//~ var url = '/translate?q=' + encodeURI(text)
	//~ + '&source=' + sourceLanguage + '&target=' + targetLanguage + '&segment_id='+segmentId.toString();
	//~ $.ajax({
	//~ url: url,
	//~ dataType: 'json',
	//~ }).success(function(response) {
	//~ if (response.data.translations.length > 0) {
	//~ callback.call(context, response.segment_id, response.data.translations[0].translatedText);
	//~ }
	//~ });
	//~ } else {
	//~ callback.call(context, segmentId, text);
	//~ }
	//~ }

	/**
	 * play(): play the media from the current location.
	 * play(time): play the media from the given time.
	 * play(segment): play the media from the given segment.
	 */
	magor.MagorPlayer.prototype.play = function(obj) {
		var media = this.$_media.get(0);
		var player = this.$_player;
		if (obj) {
			var time = parseInt(obj);
			if (!isNaN(time)) {
				this.currentTime(time);
			} else {
				var segment = obj;
				this.currentTime(segment.startTime);
			}
		}
		setTimeout(function () {
			// Resume play if the element if is paused
			if (media.paused)
				media.play();
			if(player.hasClass("paused"))
				player.removeClass("paused");
		}, 150);
	}

	magor.MagorPlayer.prototype.pause = function() {
		var media = this.$_media.get(0);
		var player = this.$_player;
		setTimeout(function () {
			// Pause if the element if is playing
			if (!media.paused)
				media.pause();
			if(!player.hasClass("paused"))
				player.addClass("paused");
		}, 150);
	}
})(magor);