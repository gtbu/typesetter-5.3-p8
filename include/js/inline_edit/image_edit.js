// v 13.9.26

function ImageEditor(area_id, section_object){

	var edit_img = null;
	var $edit_img = null;
	var saved_data = '';

	var field_w = null;
	var field_h = null;
	var field_x = null;
	var field_y = null;
	var field_a = null;

	var anim_values = {
		posx: 0,
		posy: 0,
		height: 0,
		width: 0
	};

	var anim_freq = 100;
	var timeout = null;


	// Construct
	this.save_path = gp_editing.get_path(area_id);
	$edit_img = gp_editing.get_edit_area(area_id);
	edit_img = $edit_img.get(0);

	$edit_img.addClass('gp_image_edit');


	var save_obj = {
		src: $edit_img.attr('src'),
		alt: $edit_img.attr('alt'),
		posx: 0,
		posy: 0,
		width: 0,
		height: 0
	};


	// gpEasy 4.6a2+
	// use the original image
	if(section_object.orig_src){
		save_obj.src = section_object.orig_src;
		save_obj.alt = section_object.attributes.alt;
		save_obj.posx = section_object.posx;
		save_obj.posy = section_object.posy;
		save_obj.width = section_object.attributes.width;
		save_obj.height = section_object.attributes.height;
	}


	// Load the editing options from PHP
	var path = strip_from(this.save_path,'?') + '?cmd=image_editor';
	$gp.jGoTo(path);


	// Return serialized data to be used with the save POST
	function SaveData(){

		save_obj.posx = field_x.value;
		save_obj.posy = field_y.value;

		save_obj.width = field_w.value;
		save_obj.height = field_h.value;

		save_obj.alt = field_a.value;

		return $.param(save_obj) + '&cmd=save_inline';
	}

	this.SaveData = SaveData;


	// Check to see if there is unsaved data
	this.checkDirty = function(){

		if(saved_data != SaveData()){
			return true;
		}

		return false;
	};


	// Reset the dirty state
	this.resetDirty = function(){
		saved_data = SaveData();
	};


	// Wake up this editor object
	this.wake = function(){

		if(timeout !== null){
			window.clearInterval(timeout);
		}

		timeout = window.setInterval(
			Animate,
			anim_freq
		);

		$gp.response.image_options_loaded = ImagesLoaded;
		$gp.response.gp_gallery_images = MultipleFileHandler;

		$gp.links.show_uploaded_images = function(){
			LoadImages(false);
		};

		$gp.links.gp_gallery_add = UseImage;
		$gp.links.deafult_sizes = ShowImages;

		$gp.$win.trigger('resize');
	};


	this.sleep = function(){

		if(timeout !== null){
			window.clearInterval(timeout);
			timeout = null;
		}
	};


	// Animate dimension changes
	function Animate(){

		if(!field_w || !field_h || !field_x || !field_y){
			return;
		}

		var cssText =
			'background-image:url("' +
			$gp.htmlchars(save_obj.src) +
			'");';

		// height/width
		anim_values.width = AnimValue(
			field_w.value,
			anim_values.width
		);

		anim_values.height = AnimValue(
			field_h.value,
			anim_values.height
		);

		cssText +=
			'width:' + anim_values.width +
			'px !important;' +
			'height:' + anim_values.height +
			'px !important;';

		// position
		anim_values.posx = AnimValue(
			field_x.value,
			anim_values.posx
		);

		anim_values.posy = AnimValue(
			field_y.value,
			anim_values.posy
		);

		cssText +=
			'background-position:' +
			anim_values.posx + 'px ' +
			anim_values.posy + 'px;';

		edit_img.style.cssText = cssText;
	}


	// Get amount we should animate by
	function AnimValue(desired, current){

		desired = parseInt(desired, 10);
		current = parseInt(current, 10);

		if(isNaN(desired)){
			desired = 0;
		}

		if(isNaN(current)){
			current = 0;
		}

		if(desired == current){
			return desired;
		}

		if(desired > current){
			return current + Math.min(
				20,
				desired - current
			);
		}

		return current - Math.min(
			20,
			current - desired
		);
	}


	// Set up editing display after content has loaded from PHP
	function ImagesLoaded(){

		field_w = $('#gp_current_image input[name="width"]').get(0);
		field_h = $('#gp_current_image input[name="height"]').get(0);
		field_x = $('#gp_current_image input[name="left"]').get(0);
		field_y = $('#gp_current_image input[name="top"]').get(0);
		field_a = $('#gp_current_image input[name="alt"]').get(0);


		if(
			!field_w ||
			!field_h ||
			!field_x ||
			!field_y ||
			!field_a
		){
			return;
		}


		field_x.value = save_obj.posx;
		field_y.value = save_obj.posy;

		field_w.value = save_obj.width;
		field_h.value = save_obj.height;

		field_a.value = save_obj.alt;


		gp_editing.CreateTabs();

		LoadImages(false);


		/*
		 * IMPORTANT:
		 *
		 * Do NOT use:
		 *
		 *     $edit_img.width()
		 *     $edit_img.height()
		 *
		 * here.
		 *
		 * Those values represent the current CSS/layout size
		 * of the editable area, not the saved image dimensions.
		 *
		 * Example:
		 *
		 * saved image:       240 x 120
		 * editable area:     540 x 270
		 *
		 * Using .width()/.height() here caused the editor to
		 * replace 240 x 120 with 540 x 270.
		 *
		 * Use the saved field values instead.
		 */
		anim_values.width = parseInt(
			field_w.value,
			10
		) || 0;

		anim_values.height = parseInt(
			field_h.value,
			10
		) || 0;


		/*
		 * Set the image/background but do not overwrite
		 * field_w and field_h.
		 */
		SetCurrentImage(
			save_obj.src,
			save_obj.alt,
			0,
			0
		);


		SetupDrag();


		// Change src to blank and use background image
		$edit_img.attr(
			'src',
			gp_blank_img
		);

		$edit_img.attr(
			'alt',
			gp_blank_img.split('/').pop()
		);


		saved_data = SaveData();


		// Up/down arrows
		$('#gp_current_image input').on(
			'keydown',
			function(evt){

				switch(evt.which){

					case 38: // up
						this.value =
							parseInt(this.value, 10) + 1;
						break;

					case 40: // down
						this.value =
							parseInt(this.value, 10) - 1;
						break;
				}
			}
		);
	}


	// Initialize image dragging
	function SetupDrag(){

		var posx =
			parseInt(field_x.value, 10) || 0;

		var posy =
			parseInt(field_y.value, 10) || 0;

		var mouse_startx = 0;
		var mouse_starty = 0;

		var pos_startx = 0;
		var pos_starty = 0;

		var mousedown = false;


		$edit_img
			.on('mousedown', function(evt){

				evt.preventDefault();

				mousedown = true;

				pos_startx = posx =
					parseInt(field_x.value, 10) || 0;

				pos_starty = posy =
					parseInt(field_y.value, 10) || 0;

				mouse_startx = evt.pageX;
				mouse_starty = evt.pageY;
			})

			.on(
				'mouseleave mouseup',
				function(evt){

					evt.preventDefault();

					mousedown = false;
				}
			)

			.on('mousemove', function(evt){

				if(!mousedown){
					return;
				}

				posx =
					pos_startx +
					evt.pageX -
					mouse_startx;

				posy =
					pos_starty +
					evt.pageY -
					mouse_starty;

				SetPosition(
					posx,
					posy
				);
			});
	}


	// Set the current image
	function SetCurrentImage(
		src,
		alt,
		width,
		height
	){

		save_obj.src = src;
		save_obj.alt = alt;


		$edit_img.css(
			'background-image',
			'url("' +
			$gp.htmlchars(save_obj.src) +
			'")'
		);


		$('#gp_current_image img').attr({
			src: save_obj.src,
			alt: save_obj.alt
		});


		/*
		 * Only change the fields when explicit dimensions
		 * were supplied.
		 *
		 * ImagesLoaded() calls this function with 0,0 so
		 * that the saved width/height remain untouched.
		 */
		if(
			width > 0 &&
			height > 0
		){

			field_w.value = width;
			field_h.value = height;
			field_a.value = alt;
		}
	}


	// Set the x & y position of the image
	function SetPosition(posx, posy){

		field_x.value = posx;
		field_y.value = posy;
	}


	// Add file upload handlers after the form is loaded
	function MultipleFileHandler(){

		$('#gp_upload_form').find('.file').auto_upload({

			start: function(name, settings){

				settings.bar = $(
					'<a data-cmd="gp_file_uploading"></a>'
				);

				settings.bar.text(name);

				settings.bar.appendTo(
					'#gp_upload_queue'
				);

				return true;
			},


			progress: function(
				progress,
				name,
				settings
			){

				progress =
					Math.round(
						progress * 100
					);

				progress = Math.min(
					98,
					Math.max(
						0,
						progress - 1
					)
				);

				if(settings.bar){
					settings.bar.text(
						progress + '% ' + name
					);
				}
			},


			finish: function(
				response,
				name,
				settings
			){

				var progress_bar =
					settings.bar;

				if(progress_bar){
					progress_bar.text(
						'100% ' + name
					);
				}


				var $contents = $(response);

				var status =
					$contents
						.find('.status')
						.val();

				var message =
					$contents
						.find('.message')
						.val();


				if(status == 'success'){

					if(progress_bar){
						progress_bar.addClass(
							'success'
						);

						progress_bar.slideUp(
							1200
						);
					}

					var avail =
						$('#gp_gallery_avail_imgs');

					$(message).appendTo(
						avail
					);

				}else if(status == 'notimage'){

					if(progress_bar){
						progress_bar.addClass(
							'success'
						);
					}

				}else{

					if(progress_bar){
						progress_bar.addClass(
							'failed'
						);

						progress_bar.text(
							name + ': ' + message
						);
					}
				}
			},


			error: function(
				name,
				error,
				settings
			){

				if(settings && settings.bar){

					settings.bar.addClass(
						'failed'
					);

					settings.bar.text(
						name + ': ' + error
					);

				}else{

					alert(
						'error: ' + error
					);
				}
			}
		});
	}


	// Use an image
	function UseImage(evt){

		evt.preventDefault();

		var $this =
			$(this).stop(true, true);

		var width =
			$this.data('width');

		var height =
			$this.data('height');

		var href =
			$this.attr('href');

		var alt =
			href
				.split('/')
				.pop()
				.split('_')
				.join(' ');


		var dot =
			alt.lastIndexOf('.');

		if(dot > 0){
			alt =
				alt.substring(
					0,
					dot
				);
		}


		SetCurrentImage(
			href,
			alt,
			width,
			height
		);

		SetPosition(
			0,
			0
		);
	}


	// Show Images
	function ShowImages(){

		var img = $('<img>');

		img.css({
			height: 'auto',
			width: 'auto',
			padding: 0
		});

		img.attr({
			src: save_obj.src,
			alt: save_obj.alt
		});

		img.appendTo('body');


		field_w.value = img.width();
		field_h.value = img.height();

		field_a.value = img.attr('alt');


		SetPosition(
			0,
			0
		);


		img.remove();
	}
}


/**
 * Initialize inline edit
 */
function gp_init_inline_edit(
	area_id,
	section_object,
	options
){

	// Show edit window
	$gp.LoadStyle(
		'/include/css/inline_image.css'
	);

	$gp.loaded();

	gp_editing.editor_tools();


	// Create gp_editor object
	gp_editor =
		new ImageEditor(
			area_id,
			section_object
		);
}