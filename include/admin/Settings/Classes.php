<?php

namespace gp\admin\Settings;

defined('is_running') or die('Not an entry point...');

class Classes extends \gp\special\Base{

	var $admin_link;

	function __construct($args){

		parent::__construct($args);

		$this->admin_link = \gp\tool::GetUrl('Admin/Classes');

		$cmd = \gp\tool::GetCommand();
		switch($cmd){
			case 'SaveClasses':
				$this->SaveClasses();
			break;
		}
		$this->ClassesForm();
	}


	/**
	 * Get the current classes
	 *
	 */
	public static function GetClasses(){

		$classes		= \gp\tool\Files::Get('_config/classes');
		if( $classes ){
			array_walk_recursive($classes, function($value){
				return htmlspecialchars($value, ENT_COMPAT, 'UTF-8');
			});
			return $classes;
		}

		//defaults
		return self::Defaults();
	}



	public static function Defaults(){
		return array(
			array(
				'names'		=> 'gpRow',
				'desc'		=> \CMS_NAME.' Grid Row - for wrapper sections',
			),
			array(
				'names'		=> 'gpCol-1 gpCol-2 gpCol-3 gpCol-4 gpCol-5 gpCol-6 gpCol-7 gpCol-8 gpCol-9 gpCol-10 gpCol-11 gpCol-12',
				'desc'		=> \CMS_NAME.' Grid Columns - for content sections',
			),
		);
	}



	public static function Bootstrap3(){
		return array (
			array (
				'names'		=> 'jumbotron',
				'desc'		=> 'Bootstrap: everything big for calling extra attention to some special content',
			),
			array (
				'names'		=> 'text-left text-center text-right text-justify',
				'desc'		=> 'Bootstrap: section text alignment',
			),
			array (
				'names'		=> 'text-muted text-primary text-success text-info text-warning text-danger',
				'desc'		=> 'Bootstrap text color classes: will color the entire text in the section (unless otherwise specified)',
			),
			array (
				'names'		=> 'bg-primary bg-success bg-info bg-warning bg-danger',
				'desc'		=> 'Bootstrap background color classes: darker backgrounds will also need e.g. text-white',
			),
			array (
				'names'		=> 'row container container-fluid',
				'desc'		=> 'Bootstrap Grid: use with Wrapper Sections',
			),
			array (
				'names'		=> 'col-xs-1 col-xs-2 col-xs-3 col-xs-4 col-xs-5 col-xs-6 col-xs-7 col-xs-8 col-xs-9 col-xs-10 col-xs-11 col-xs-12',
				'desc'		=> 'Bootstrap Grid: column width (mobile first)',
			),
			array (
				'names'		=> 'col-sm-1 col-sm-2 col-sm-3 col-sm-4 col-sm-5 col-sm-6 col-sm-7 col-sm-8 col-sm-9 col-sm-10 col-sm-11 col-sm-12',
				'desc'		=> 'Bootstrap Grid: column width on tablets (screen width ≥ 768px)',
			),
			array (
				'names'		=> 'col-md-1 col-md-2 col-md-3 col-md-4 col-md-5 col-md-6 col-md-7 col-md-8 col-md-9 col-md-10 col-md-11 col-md-12',
				'desc'		=> 'Bootstrap Grid: column width on laptops (screen width ≥ 992px)',
			),
			array (
				'names'		=> 'col-lg-1 col-lg-2 col-lg-3 col-lg-4 col-lg-5 col-lg-6 col-lg-7 col-lg-8 col-lg-9 col-lg-10 col-lg-11 col-lg-12',
				'desc'		=> 'Bootstrap Grid: column width on desktops (screen width ≥ 1200px)',
			),
			array (
				'names'		=> 'col-xs-push-1 col-xs-push-2 col-xs-push-3 col-xs-push-4 col-xs-push-5 col-xs-push-6 col-xs-push-7 col-xs-push-8 col-xs-push-9 col-xs-push-10 col-xs-push-11',
				'desc'		=> 'Bootstrap Grid: push colum to the right (mobile first)',
			),
			array (
				'names'		=> 'col-sm-push-0 col-sm-push-1 col-sm-push-2 col-sm-push-3 col-sm-push-4 col-sm-push-5 col-sm-push-6 col-sm-push-7 col-sm-push-8 col-sm-push-9 col-sm-push-10 col-sm-push-11',
				'desc'		=> 'Bootstrap Grid: push colum to the right on tablets (screen width ≥ 768px)',
			),
			array (
				'names'		=> 'col-md-push-0 col-md-push-1 col-md-push-2 col-md-push-3 col-md-push-4 col-md-push-5 col-md-push-6 col-md-push-7 col-md-push-8 col-md-push-9 col-md-push-10 col-md-push-11',
				'desc'		=> 'Bootstrap Grid: push colum to the right on laptops (screen width ≥ 992px)',
			),
			array (
				'names'		=> 'col-lg-push-0 col-lg-push-1 col-lg-push-2 col-lg-push-3 col-lg-push-4 col-lg-push-5 col-lg-push-6 col-lg-push-7 col-lg-push-8 col-lg-push-9 col-lg-push-10 col-lg-push-11',
				'desc'		=> 'Bootstrap Grid: push colum to the right on desktops (screen width ≥ 1200px)',
			),
			array (
				'names'		=> 'col-xs-pull-1 col-xs-pull-2 col-xs-pull-3 col-xs-pull-4 col-xs-pull-5 col-xs-pull-6 col-xs-pull-7 col-xs-pull-8 col-xs-pull-9 col-xs-pull-10 col-xs-pull-11',
				'desc'		=> 'Bootstrap Grid: pull colum to the left (mobile first)',
			),
			array (
				'names'		=> 'col-sm-pull-0 col-sm-pull-1 col-sm-pull-2 col-sm-pull-3 col-sm-pull-4 col-sm-pull-5 col-sm-pull-6 col-sm-pull-7 col-sm-pull-8 col-sm-pull-9 col-sm-pull-10 col-sm-pull-11',
				'desc'		=> 'Bootstrap Grid: pull colum to the left on tablets (screen width ≥ 768px)',
			),
			array (
				'names'		=> 'col-md-pull-0 col-md-pull-1 col-md-pull-2 col-md-pull-3 col-md-pull-4 col-md-pull-5 col-md-pull-6 col-md-pull-7 col-md-pull-8 col-md-pull-9 col-md-pull-10 col-md-pull-11',
				'desc'		=> 'Bootstrap Grid: pull colum to the left on laptops (screen width ≥ 992px)',
			),
			array (
				'names'		=> 'col-lg-pull-0 col-lg-pull-1 col-lg-pull-2 col-lg-pull-3 col-lg-pull-4 col-lg-pull-5 col-lg-pull-6 col-lg-pull-7 col-lg-pull-8 col-lg-pull-9 col-lg-pull-10 col-lg-pull-11',
				'desc'		=> 'Bootstrap Grid: pull colum to the left on desktops (screen width ≥ 1200px)',
			),
			array (
				'names'		=> 'col-xs-offset-1 col-xs-offset-2 col-xs-offset-3 col-xs-offset-4 col-xs-offset-5 col-xs-offset-6 col-xs-offset-7 col-xs-offset-8 col-xs-offset-9 col-xs-offset-10 col-xs-offset-11',
				'desc'		=> 'Bootstrap Grid: offset colum to the right (mobile first)',
			),
			array (
				'names'		=> 'col-sm-offset-0 col-sm-offset-1 col-sm-offset-2 col-sm-offset-3 col-sm-offset-4 col-sm-offset-5 col-sm-offset-6 col-sm-offset-7 col-sm-offset-8 col-sm-offset-9 col-sm-offset-10 col-sm-offset-11',
				'desc'		=> 'Bootstrap Grid: offset colum to the right on tablets (screen width ≥ 768px)',
			),
			array (
				'names'		=> 'col-md-offset-0 col-md-offset-1 col-md-offset-2 col-md-offset-3 col-md-offset-4 col-md-offset-5 col-md-offset-6 col-md-offset-7 col-md-offset-8 col-md-offset-9 col-md-offset-10 col-md-offset-11',
				'desc'		=> 'Bootstrap Grid: offset colum to the right on laptops (screen width ≥ 992px)',
			),
			array (
				'names'		=> 'col-lg-offset-0 col-lg-offset-1 col-lg-offset-2 col-lg-offset-3 col-lg-offset-4 col-lg-offset-5 col-lg-offset-6 col-lg-offset-7 col-lg-offset-8 col-lg-offset-9 col-lg-offset-10 col-lg-offset-11',
				'desc'		=> 'Bootstrap Grid: offset colum to the right on desktops (screen width ≥ 1200px)',
			),
			array (
				'names'		=> 'visible-xs-block visible-sm-block visible-md-block visible-lg-block',
				'desc'		=> 'Bootstrap Visibility: using this classes will make the section visible <strong>only</strong> on the specified breakpoint / screen width (xs, sm, md or lg)',
			),
			array (
				'names'		=> 'hidden-xs hidden-sm hidden-md hidden-lg',
				'desc'		=> 'Bootstrap Visibility: using this classes will hide the section <strong>only</strong> on the specified breakpoint / screen width (xs, sm, md or lg)',
			),
		);
	}


	public static function Bootstrap4(){

		$cols_count = 12;
		$breakpoints = [ 'xs', 'sm', 'md', 'lg', 'xl' ];
		$colors		= [ 'primary', 'secondary', 'success', 'danger', 'warning', 'info', 'light', 'dark' ];
		$spacers	= range(0, 5);
		$margins	= array_merge(range(0, 5), ['auto', 'n1', 'n2', 'n3', 'n4', 'n5']);
		$cols		= array_merge([''], range(1, $cols_count), ['auto']);
		$offsets	= range(0, $cols_count-1);

		$bs4 = []; // array that will be returned

		function addSet(&$bs4, $desc, $name, $bps, $vals){ // $bs4 is passed by reference!
			$breakpoint_descs = [
				'xs' => '(mobile first)',
				'sm' => 'on large smartphones (screen width ≥ 576px)',
				'md' => 'on tablets (screen width ≥ 786px)',
				'lg' => 'on laptops (screen width ≥ 992px)',
				'xl' => 'on desktops (screen width ≥ 1200px)',
			];
			foreach( $bps as $i => $bp ){
				$names = [];
				$d = $desc;
				$bpn = $bp == 'xs' || $bp == '' ? '' : '-' . $bp;
				foreach( $vals as $val ){
					if( $bp == 'xs' && $name == 'offset' && $val == 0 ){
						continue;
					}
					$names[] = $name . $bpn . ($val !== '' ? '-' : '') . $val;
				}
				if( !empty($bp) && !empty($breakpoint_descs[$bp]) ){
					$d .= ' ' . $breakpoint_descs[$bp];
				}
				$bs4[] = [
					'names'	=> implode(' ', $names),
					'desc'	=> $d,
				];
			}
		}

		// text
		addSet(
			$bs4,
			'BS4: text alignment',
			'text',
			$breakpoints,
			['left', 'center', 'right', 'justify']
		);
		$bs4[] = [
			'names'	=> 'text-primary text-secondary text-success text-danger text-warning ' .
						 'text-info text-light text-dark text-white text-body text-muted ' .
						 'text-black-50 text-white-50 text-reset',
			'desc'	=> 'BS4 text utils: colors the entire text in the section (unless otherwise specified)',
		];
		$bs4[] = [
			'names'	=> 'font-weight-normal font-weight-bold font-weight-bolder font-weight-light font-weight-lighter',
			'desc'	=> 'BS4 text utils: apply different font weights',
		];
		$bs4[] = [
			'names'	=> 'font-italic',
			'desc'	=> 'BS4 text utils: use italic font style',
		];
		$bs4[] = [
			'names'	=> 'text-monospace',
			'desc'	=> 'BS4 text utils: use monospace font (stack) defined in variables.scss',
		];
		$bs4[] = [
			'names'	=> 'lead',
			'desc'	=> 'BS4 text utils: makes paragraphs <p> inside the section stand out. Does not influence headings and other elements with defined font sizes',
		];
		$bs4[] = [
			'names'	=> 'small',
			'desc'	=> 'BS4 text utils: makes text inside the section smaller. Does not influence headings and other elements with defined font sizes',
		];
		$bs4[] = [
			'names'	=> 'text-lowercase text-uppercase text-capitalize',
			'desc'	=> 'BS4 text utils: use text-transform to change case',
		];
		$bs4[] = [
			'names'	=> 'text-nowrap text-truncate',
			'desc'	=> 'BS4 text utils: prevent text from wrapping or truncate it',
		];
		$bs4[] = [
			'names'	=> 'text-break',
			'desc'	=> 'BS4 text utils: force long words to break at the section boundaries',
		];

		// background colors
		$bs4[] = [
			'names'	=> 'bg-primary bg-secondary bg-success bg-danger bg-warning bg-info bg-light bg-dark bg-white bg-transparent',
			'desc'	=> 'BS4 background colors: darker backgrounds will also need e.g. text-white',
		];

		/*
		// background gradients (disabled by default)
		$bs4[] = [
			'names'	=> 'bg-gradient-primary bg-gradient-secondary bg-gradient-success bg-gradient-danger bg-gradient-warning bg-gradient-info bg-gradient-light bg-gradient-dark',
			'desc'	=> 'BS4 background gradients: only works with $enable-gradients: true; in variables.scss'
		];
		*/

		// containers + rows
		$bs4[] = [
			'names'	=> 'row container container-fluid',
			'desc'	=> 'BS4 layout/grid: to be used with wrapper sections',
		];

		// row-cols
		addSet(
			$bs4,
			'BS4 grid: use together with ‘row’ to control how many col child sections appear next to each other',
			'row-cols',
			$breakpoints,
			range(1, $cols_count)
		);

		// no-gutters
		$bs4[] = [
			'names'	=> 'no-gutters',
			'desc'	=> 'BS4 grid: use together with ‘row’ to remove its negative margins ' .
						 'and the horizontal padding from all immediate child cols',
		];

		// columns
		addSet(
			$bs4,
			'BS4 grid: column width (in twelfths)',
			'col',
			$breakpoints,
			$cols
		);

		// offsets
		addSet(
			$bs4,
			'BS4 grid: offset a colum to the right (in twelfths)',
			'offset',
			$breakpoints,
			$offsets
		);

		// display
		addSet(
			$bs4,
			'BS4 display utils: e.g. use d-none to hide an element',
			'd',
			$breakpoints,
			['none', 'flex', 'inline-flex', 'block', 'inline', 'inline-block', 'table', 'table-cell', 'table-row']
		);

		/*
		// vertical-align
		// Disabled for being potentially misleading with sections,
		// which are very unlinkely to be inline level or table cells
		$bs4[] = [
			'names'	=> 'align-baseline align-top align-middle align-bottom align-text-bottom  align-text-top',
			'desc'	=> 'BS4 alignment utils: change vertical alignment of a section. ' .
						'Only works with d-inline, d-inline-block, d-inline-table or table-cell',
		];
		*/

		// flex
		addSet(
			$bs4,
			'BS4 flex utils: direction of flex items in a flex container',
			'flex',
			$breakpoints,
			['row', 'column', 'row-reverse', 'column-reverse']
		);
		addSet(
			$bs4,
			'BS4 flex utils: change how flex items wrap in a flex container',
			'flex',
			$breakpoints,
			['wrap', 'nowrap', 'wrap-reverse']
		);
		addSet(
			$bs4,
			'BS4 flex utils: change the alignment of flex items on the main axis (flex-row=horizontal, flex-column=vertical)',
			'justify-content',
			$breakpoints,
			['start', 'end', 'center', 'between', 'around']
		);
		addSet(
			$bs4,
			'BS4 flex utils: changes how flex items align together on the cross axis (flex-row=vertical, flex-column=horizontal)',
			'align-content',
			$breakpoints,
			['start', 'end', 'center', 'around', 'stretch']
		);
		addSet(
			$bs4,
			'BS4 flex utils: change the alignment of flex items on the cross axis (flex-row=vertical, flex-column=horizontal)',
			'align-items',
			$breakpoints,
			['start', 'end', 'center', 'baseline', 'stretch']
		);
		addSet(
			$bs4,
			'BS4 flex utils: use on flexbox items to individually change their alignment on the cross axis',
			'align-self',
			$breakpoints,
			['start', 'end', 'center', 'baseline', 'stretch']
		);
		addSet(
			$bs4,
			'BS4 flex utils: use on series of sibling elements to force them into widths equal to their content (similar to table cells)',
			'flex',
			$breakpoints,
			['fill']
		);
		addSet(
			$bs4,
			'BS4 flex utils: toggle a flex item’s ability to grow to fill available space',
			'flex',
			$breakpoints,
			['grow-0', 'grow-1']
		);
		addSet(
			$bs4,
			'BS4 flex utils: toggle a flex item’s ability to shrink if necessary',
			'flex',
			$breakpoints,
			['shrink-0', 'shrink-1']
		);

		// cards
		$bs4[] = [
			'names'	=> 'card-columns card-deck card-group',
			'desc'	=> 'BS4 card layout wrappers: use for wrapper sections that contain ‘card’ sections. ' .
						'card-columns: a pinterest-like masonry, ' .
						'card-deck: grid of cards of equal height and width, ' .
						'card-group: similar to grid but without gutters',
		];
		$bs4[] = [
			'names'	=> 'card',
			'desc'	=> 'BS4 card element: use this class on wrapper sections',
		];
		$bs4[] = [
			'names'	=> 'card-header card-body card-footer',
			'desc'	=> 'BS4 card content: use for child sections inside wrapper sections with the ‘card’ class',
		];
		$bs4[] = [
			'names'	=> 'card-img card-img-top card-img-bottom',
			'desc'	=> 'BS4 card content: use for child image sections inside wrapper sections with the ‘card’ class',
		];
		$bs4[] = [
			'names'	=> 'card-img-overlay',
			'desc'	=> 'BS4 card content: use for child sections inside wrapper sections with the ‘card’ class. The section must follow a ‘card-image’ section so its content can overlay the image',
		];
		$bs4[] = [
			'names'	=> 'card-title card-subtitle card-text',
			'desc'	=> 'BS4 card content: use for child sections inside wrapper sections with the ‘card-header -body or -footer’ classes',
		];


		// alerts
		$bs4[] = [
			'names'	=> 'alert',
			'desc'	=> 'BS4 alert: a message-box-style section. Use together with alert-color classes',
		];
		addSet(
			$bs4,
			'BS4 alerts: color classes to be used together with the ‘alert’ class',
			'alert',
			[''],
			$colors
		);


		// overflow
		$bs4[] = [
			'names'	=> 'overflow-auto overflow-hidden',
			'desc'	=> 'BS4 utils: determines how content overflows the section',
		];

		// position
		$bs4[] = [
			'names'	=> 'position-relative position-absolute position-fixed position-sticky position-static fixed-top fixed-bottom sticky-top',
			'desc'	=> 'BS4 utils: determines the positioning of the section',
		];

		//paddings
		addSet(
			$bs4,
			'BS4 sizing utils: set padding on all 4 sides',
			'p',
			$breakpoints,
			$spacers
		);
		addSet(
			$bs4,
			'BS4 sizing utils: set both padding-left and padding-right',
			'px',
			$breakpoints,
			$spacers
		);
		addSet(
			$bs4,
			'BS4 sizing utils: set padding-left',
			'pl',
			$breakpoints,
			$spacers
		);
		addSet(
			$bs4,
			'BS4 sizing utils: set padding-right',
			'pr',
			$breakpoints,
			$spacers
		);
		addSet(
			$bs4,
			'BS4 sizing utils: set both padding-top and padding-bottom',
			'py',
			$breakpoints,
			$spacers
		);
		addSet(
			$bs4,
			'BS4 sizing utils: set padding-top',
			'pt',
			$breakpoints,
			$spacers
		);
		addSet(
			$bs4,
			'BS4 sizing utils: set padding-bottom',
			'pb',
			$breakpoints,
			$spacers
		);

		// margins
		addSet(
			$bs4,
			'BS4 sizing utils: set margin on all 4 sides',
			'm',
			$breakpoints,
			$margins
		);
		addSet(
			$bs4,
			'BS4 sizing utils: set both margin-left and margin-right',
			'mx',
			$breakpoints,
			$margins
		);
		addSet(
			$bs4,
			'BS4 sizing utils: set margin-left',
			'ml',
			$breakpoints,
			$margins
		);
		addSet(
			$bs4,
			'BS4 sizing utils: set margin-right',
			'mr',
			$breakpoints,
			$margins
		);
		addSet(
			$bs4,
			'BS4 sizing utils: set both margin-top and margin-bottom',
			'my',
			$breakpoints,
			$margins
		);
		addSet(
			$bs4,
			'BS4 sizing utils: set margin-top',
			'mt',
			$breakpoints,
			$margins
		);
		addSet(
			$bs4,
			'BS4 sizing utils: set margin-bottom',
			'mb',
			$breakpoints,
			$margins
		);

		// width
		addSet(
			$bs4,
			'BS4 sizing utils: quickly define or override an element’s width',
			'w',
			$breakpoints,
			['25', '50', '75', '100', 'auto']
		);

		// max-width
		addSet(
			$bs4,
			'BS4 sizing utils: quickly define or override an element’s max-width',
			'w',
			$breakpoints,
			['100']
		);

		// height
		addSet(
			$bs4,
			'BS4 sizing utils: quickly define or override an element’s height',
			'h',
			$breakpoints,
			['25', '50', '75', '100', 'auto']
		);

		// max-height
		addSet(
			$bs4,
			'BS4 sizing utils: quickly define or override an element’s max-height',
			'h',
			$breakpoints,
			['100']
		);

		// order
		addSet(
			$bs4,
			'BS4 order utils: change the visual order of the section inside its wrapper',
			'order',
			$breakpoints,
			array_merge(['order-first', 'order-last'], range(0, 12))
		);

		// border
		$bs4[] = [
			'names'	=> 'border border-top border-right border-bottom border-left',
			'desc'	=> 'BS4 border utils: add borders to an element',
		];
		$bs4[] = [
			'names'	=> 'border-0 border-top-0 border-right-0 border-bottom-0 border-left-0',
			'desc'	=> 'BS4 border utils: subtract an element’s borders',
		];
		addSet(
			$bs4,
			'BS4 border utils: change the border color',
			'border',
			[''], // not responsive but we need to pass an array
			$colors
		);

		// border-radius
		$bs4[] = [
			'names'	=> 'rounded rounded-top rounded-right rounded-bottom rounded-left rounded-circle rounded-pill rounded-0',
			'desc'	=> 'BS4 border utils: easily round an element’s corners',
		];
		$bs4[] = [
			'names'	=> 'rounded-sm rounded-lg',
			'desc'	=> 'BS4 border utils: use for larger or smaller border-radius',
		];

		/*
		// shadows (disabled by default)
		$bs4[] = [
			'names'	=> 'shadow shadow-none shadow-sm shadow-lg',
			'desc'	=> 'BS4: change shadow display and size added via box-shadow utility classes. Requires $enable-shadows: true; in variables.scss',
		];
		*/

		// jumbotron
		$bs4[] = [
			'names'	=> 'jumbotron',
			'desc'	=> 'BS4: everything big for calling extra attention to some special content',
		];
		$bs4[] = [
			'names'	=> 'jumbotron-fluid',
			'desc'	=> 'BS4: combine with jumbotron for full-width sections without rounded corners',
		];

		// float
		addSet(
			$bs4,
			'BS4 float utils: toggle floats on the section',
			'float',
			$breakpoints,
			['left', 'right', 'none']
		);

		// clearfix
		$bs4[] = [
			'names'	=> 'clearfix',
			'desc'	=> 'BS clearfix: use for wrapper sections that contain floated child sections',
		];

		// visibility
		$bs4[] = [
			'names'	=> 'visible invisible',
			'desc'	=> 'BS4 visibility: control the visibility without modifying the display. Invisible elements will still take up space in the page',
		];

		// screen readers only
		$bs4[] = [
			'names'	=> 'sr-only',
			'desc'	=> 'BS4 screen reader utils: hide elements on all devices except screen readers',
		];
		$bs4[] = [
			'names'	=> 'sr-only-focusable',
			'desc'	=> 'BS4 screen reader utils: combine with sr-only to show the element again when it’s focused (e.g. via keyboard)',
		];

		// print
		$bs4[] = [
			'names'	=> 'd-print-none d-print-inline d-print-inline-block d-print-block d-print-table d-print-table-row d-print-table-cell d-print-flex d-print-inline-flex',
			'desc'	=> 'BS4 print utils: change the display value of elements when printing',
		];

		return $bs4;
	}

     /**
	 * Return list of classes for the Bootstrap 5 framework (up to v5.3)
	 * Includes features like subtle colors, link utilities, stacks, focus rings, etc.
	 * @return array
	 */
	public static function Bootstrap5(){

		$cols_count = 12;
		// Note: Base breakpoint (xs) uses no infix in BS5 utilities
		$breakpoints = ['', 'sm', 'md', 'lg', 'xl', 'xxl'];

		// Standard Theme Colors
		$colors		= [ 'primary', 'secondary', 'success', 'danger', 'warning', 'info', 'light', 'dark' ];
		// Body/Subtle Colors (Introduced/Expanded in 5.2/5.3)
		$body_colors = ['body', 'body-secondary', 'body-tertiary']; // Subtle background/text colors
		$subtle_colors = array_map(fn($c) => $c . '-subtle', $colors); // e.g., primary-subtle

		// Combined Color Lists for Utilities
		$all_bg_colors    = array_merge($colors, $body_colors, $subtle_colors, ['white', 'black', 'transparent']);
		$all_text_colors  = array_merge($colors, $body_colors, ['white', 'black', 'muted', 'black-50', 'white-50', 'reset', 'body-emphasis'], $subtle_colors); // Added body-emphasis, subtle text
		$all_border_colors= array_merge($colors, $subtle_colors, ['white', 'black']); // Subtle borders added 5.3

		$spacers	= range(0, 5);
		$margins	= array_merge(range(0, 5), ['auto']); // Positive margins + auto
		$neg_margins= ['n1', 'n2', 'n3', 'n4', 'n5'];   // Negative margins
		$all_margins = array_merge($margins, $neg_margins); // All margin values

		$cols		= array_merge([''], range(1, $cols_count), ['auto']); // Include plain 'col', 'col-*', 'col-auto'
		$offsets	= range(0, $cols_count - 1);
		$orders     = array_merge(['first', 'last'], range(0, 5)); // BS5 uses 0-5 for order
		$border_widths = range(0, 5); // BS5 border widths 0-5
		$rounded_sizes = range(0, 5); // BS5 rounded sizes 0-5 (0=none, 1-3=sm/default/lg, 4, 5 new in 5.3)
		$link_offsets = range(1, 3); // For link-offset-*
		$link_opacities = [10, 25, 50, 75, 100]; // For link-opacity-*

		$bs5 = []; // array that will be returned

		// Define addSet closure for BS5 - slightly enhanced
		$addSet = function(&$target_array, $desc, $name, $bps, $vals, $options = []) {
			$defaults = [
				'skip_base_zero' => false, // Skip generating class if breakpoint is base ('') and value is 0
				'val_separator' => '-',     // Separator between name and value (e.g., col-1)
				'bp_separator' => '-',      // Separator between name/bp (e.g., col-sm)
			];
			$opts = array_merge($defaults, $options);

			$breakpoint_descs = [
				''    => '(mobile first / all screens)',
				'sm'  => 'on small screens (≥ 576px)',
				'md'  => 'on medium screens (≥ 768px)',
				'lg'  => 'on large screens (≥ 992px)',
				'xl'  => 'on extra large screens (≥ 1200px)',
				'xxl' => 'on extra extra large screens (≥ 1400px)',
			];

			foreach ($bps as $bp) {
				$names = [];
				$d = $desc;
				// Prepend breakpoint separator only if breakpoint is not empty
				$bpn = $bp == '' ? '' : $opts['bp_separator'] . $bp;

				foreach ($vals as $val) {
					// Option to skip zero value for base breakpoint
					if ($opts['skip_base_zero'] && $bp == '' && ($val === 0 || $val === '0')) {
						continue;
					}
					// Handle cases where value might be empty (like plain 'col') or keywords ('auto', 'first')
					$val_str = ($val !== '' && $val !== null ? $opts['val_separator'] : '') . $val;
					$names[] = $name . $bpn . $val_str;
				}

				if (!empty($names)) { // Only add if names were generated
					if (isset($breakpoint_descs[$bp])) {
						$d .= ' ' . $breakpoint_descs[$bp];
					}
					$target_array[] = [
						'names' => implode(' ', $names),
						'desc'  => $d,
					];
				}
			}
		};

		// --- Color Modes (Bootstrap 5.3+) ---
		$bs5[] = [
			'names' => '[data-bs-theme="light"] [data-bs-theme="dark"] [data-bs-theme="auto"]',
			'desc'  => 'BS5.3+ Color Modes: Apply theme to `<html>` or component wrapper. Utilities like `*-subtle` adapt automatically. Not a class, but the core concept.',
		];


		// --- Layout ---

		// Containers
		$bs5[] = [
			'names' => 'container container-sm container-md container-lg container-xl container-xxl container-fluid',
			'desc'  => 'BS5 Containers: Control the max-width of the layout. Use container-fluid for full width.',
		];

		// Grid: Rows
		$bs5[] = [
			'names'	=> 'row',
			'desc'	=> 'BS5 Grid: Wrapper for columns. Use with `col-*` classes inside.',
		];

		// Grid: Columns
		$addSet($bs5, 'BS5 Grid: Column width (auto, specific fraction, or equal width). Use inside a `row`.', 'col', $breakpoints, $cols);

		// Grid: Row Columns (Control columns per row)
		$addSet($bs5, 'BS5 Grid: Set the number of columns fitting per row. Use on a `row`.', 'row-cols', $breakpoints, array_merge(['auto'], range(1, 6))); // Auto + common values

		// Grid: Offsets
		$addSet($bs5, 'BS5 Grid: Offset a column to the right (in twelfths).', 'offset', $breakpoints, $offsets, ['skip_base_zero' => true]);

		// Grid: Gutters (Spacing between columns)
		$addSet($bs5, 'BS5 Grid: Set gutter width (padding on columns)', 'g', $breakpoints, $spacers);
		$addSet($bs5, 'BS5 Grid: Set horizontal gutter width', 'gx', $breakpoints, $spacers);
		$addSet($bs5, 'BS5 Grid: Set vertical gutter width', 'gy', $breakpoints, $spacers);

		// Stacks (New in BS5, vertical/horizontal layout helpers)
		$bs5[] = [
			'names' => 'vstack',
			'desc'  => 'BS5 Layout Stacks: Creates a vertical flexbox layout. Use with `gap-*` utilities.',
		];
		$bs5[] = [
			'names' => 'hstack',
			'desc'  => 'BS5 Layout Stacks: Creates an inline horizontal flexbox layout. Use with `gap-*` utilities.',
		];
		$addSet($bs5, 'BS5 Layout Stacks: Adds space between items in a stack or grid.', 'gap', $breakpoints, $spacers); // Gap utility for stacks/grid

		// Clearfix
		$bs5[] = [
			'names'	=> 'clearfix',
			'desc'	=> 'BS5 Clearfix: Contains floats within a wrapper section.',
		];


		// --- Content & Typography ---

		// Headings (Classes match elements)
		$bs5[] = [
			'names' => 'h1 h2 h3 h4 h5 h6',
			'desc'  => 'BS5 Typography: Style any text like a heading element.',
		];
		// Display Headings
		$bs5[] = [
			'names' => 'display-1 display-2 display-3 display-4 display-5 display-6',
			'desc'  => 'BS5 Typography: Larger, more opinionated headings.',
		];

		// Text Alignment
		$addSet($bs5, 'BS5 Text: Alignment (respects LTR/RTL)', 'text', $breakpoints, ['start', 'center', 'end']);
		$bs5[] = [
			'names' => 'text-justify', // Non-responsive justify
			'desc'  => 'BS5 Text: Justify paragraph text (applies to all screen sizes).'
		];

		// Text Wrapping & Breaking
		$bs5[] = [
			'names'	=> 'text-wrap text-nowrap',
			'desc'	=> 'BS5 Text: Control text wrapping.',
		];
		$bs5[] = [
			'names'	=> 'text-truncate',
			'desc'	=> 'BS5 Text: Truncate long text with an ellipsis.',
		];
		$bs5[] = [
			'names'	=> 'text-break',
			'desc'	=> 'BS5 Text: Prevent long words from breaking layout by forcing wrap.',
		];

		// Text Transform
		$bs5[] = [
			'names'	=> 'text-lowercase text-uppercase text-capitalize',
			'desc'	=> 'BS5 Text: Transform text case.',
		];

		// Font Size
		$bs5[] = [
			'names'	=> 'fs-1 fs-2 fs-3 fs-4 fs-5 fs-6',
			'desc'	=> 'BS5 Typography: Responsive font sizes, scaling with viewport.',
		];

		// Font Weight & Style
		$bs5[] = [
			'names'	=> 'fw-bold fw-bolder fw-semibold fw-normal fw-light fw-lighter',
			'desc'	=> 'BS5 Text: Font weight utilities.',
		];
		$bs5[] = [
			'names'	=> 'fst-italic fst-normal',
			'desc'	=> 'BS5 Text: Font style utilities.',
		];

		// Line Height
		$bs5[] = [
			'names'	=> 'lh-1 lh-sm lh-base lh-lg',
			'desc'	=> 'BS5 Text: Line height utilities.',
		];

		// Monospace
		$bs5[] = [
			'names'	=> 'font-monospace',
			'desc'	=> 'BS5 Text: Use monospace font stack.',
		];

		// Reset color
		$bs5[] = [
			'names'	=> 'text-reset',
			'desc'	=> 'BS5 Text: Resets text color to inherit from parent.',
		];

		// Text Decoration
		$bs5[] = [
			'names'	=> 'text-decoration-underline text-decoration-line-through text-decoration-none',
			'desc'	=> 'BS5 Text: Add or remove text decoration.',
		];

		// Lead & Small Text
		$bs5[] = [
			'names'	=> 'lead',
			'desc'	=> 'BS5 Text: Make a paragraph stand out.',
		];
		$bs5[] = [
			'names'	=> 'small',
			'desc'	=> 'BS5 Text: Creates smaller, secondary text (like `<small>`).',
		];

		// Text Colors (Including 5.3 additions)
		$bs5[] = [
			'names'	=> implode(' ', array_map(fn($c) => "text-$c", $all_text_colors)),
			'desc'	=> 'BS5 Text Colors: Apply theme, body, subtle, muted, emphasis, white/black variations etc. Subtle colors adapt to light/dark modes.',
		];
		$bs5[] = [
			'names'	=> 'text-opacity-25 text-opacity-50 text-opacity-75 text-opacity-100',
			'desc'	=> 'BS5 Text Opacity: Control the opacity of text colors (use with `text-*` utilities).',
		];

		// --- Link Utilities (New/Expanded in 5.3) ---
		$bs5[] = [
			'names' => implode(' ', array_map(fn($c) => "link-$c", $colors)) . ' link-body-emphasis',
			'desc'  => 'BS5 Link Helpers: Set link color. `link-body-emphasis` provides high contrast.',
		];
		$addSet($bs5, 'BS5 Link Helpers: Set link offset from text (underline spacing)', 'link-offset', ['', 'hover'], $link_offsets, ['bp_separator' => '']); // e.g., link-offset-2, link-offset-2-hover
		$addSet($bs5, 'BS5 Link Helpers: Set link opacity', 'link-opacity', ['', 'hover'], $link_opacities, ['bp_separator' => '']); // e.g., link-opacity-50, link-opacity-75-hover
		$bs5[] = [
			'names' => 'link-underline',
			'desc'  => 'BS5 Link Helpers: Base class for controlling underline style/opacity/offset.',
		];
		$addSet($bs5, 'BS5 Link Helpers: Set link underline color', 'link-underline', [''], $colors, ['val_separator' => '-']); // e.g., link-underline-primary
		$addSet($bs5, 'BS5 Link Helpers: Set link underline opacity', 'link-underline-opacity', ['', 'hover'], $link_opacities, ['bp_separator' => '']); // e.g., link-underline-opacity-50

		// --- Backgrounds (Including 5.3 additions) ---
		$bs5[] = [
			'names'	=> implode(' ', array_map(fn($c) => "bg-$c", $all_bg_colors)),
			'desc'	=> 'BS5 Background Colors: Apply theme, body shades, subtle colors, white/black/transparent. Subtle colors adapt to light/dark modes.',
		];
		$bs5[] = [
			'names'	=> 'bg-opacity-10 bg-opacity-25 bg-opacity-50 bg-opacity-75 bg-opacity-100',
			'desc'	=> 'BS5 Background Opacity: Control the opacity of background colors (use with `bg-*` utilities).',
		];
		$bs5[] = [
			'names'	=> 'bg-gradient',
			'desc'	=> 'BS5 Background Gradient: Adds a subtle top-to-bottom gradient (use with `bg-*` colors). Requires $enable-gradients: true;',
		];

		// --- Text Background Helpers (New in 5.2) ---
		$bs5[] = [
			'names' => implode(' ', array_map(fn($c) => "text-bg-$c", $colors)),
			'desc'  => 'BS5 Text/Background Helpers: Set background color and contrasting text color simultaneously (e.g., for badges, alerts).',
		];

		// --- Spacing (Margin & Padding - RTL Aware) ---
		// Padding
		$addSet($bs5, 'BS5 Spacing: Set padding on all sides', 'p', $breakpoints, $spacers);
		$addSet($bs5, 'BS5 Spacing: Set padding top', 'pt', $breakpoints, $spacers);
		$addSet($bs5, 'BS5 Spacing: Set padding end (right in LTR, left in RTL)', 'pe', $breakpoints, $spacers);
		$addSet($bs5, 'BS5 Spacing: Set padding bottom', 'pb', $breakpoints, $spacers);
		$addSet($bs5, 'BS5 Spacing: Set padding start (left in LTR, right in RTL)', 'ps', $breakpoints, $spacers);
		$addSet($bs5, 'BS5 Spacing: Set horizontal padding (start and end)', 'px', $breakpoints, $spacers);
		$addSet($bs5, 'BS5 Spacing: Set vertical padding (top and bottom)', 'py', $breakpoints, $spacers);

		// Margin (Positive & Auto)
		$addSet($bs5, 'BS5 Spacing: Set margin on all sides (0-5, auto)', 'm', $breakpoints, $margins);
		$addSet($bs5, 'BS5 Spacing: Set margin top (0-5, auto)', 'mt', $breakpoints, $margins);
		$addSet($bs5, 'BS5 Spacing: Set margin end (0-5, auto)', 'me', $breakpoints, $margins);
		$addSet($bs5, 'BS5 Spacing: Set margin bottom (0-5, auto)', 'mb', $breakpoints, $margins);
		$addSet($bs5, 'BS5 Spacing: Set margin start (0-5, auto)', 'ms', $breakpoints, $margins);
		$addSet($bs5, 'BS5 Spacing: Set horizontal margin (0-5, auto)', 'mx', $breakpoints, $margins);
		$addSet($bs5, 'BS5 Spacing: Set vertical margin (0-5, auto)', 'my', $breakpoints, $margins);

		// Negative Margin
		$addSet($bs5, 'BS5 Spacing: Set negative margin top', 'mt', $breakpoints, $neg_margins);
		$addSet($bs5, 'BS5 Spacing: Set negative margin end', 'me', $breakpoints, $neg_margins);
		$addSet($bs5, 'BS5 Spacing: Set negative margin bottom', 'mb', $breakpoints, $neg_margins);
		$addSet($bs5, 'BS5 Spacing: Set negative margin start', 'ms', $breakpoints, $neg_margins);
		$addSet($bs5, 'BS5 Spacing: Set negative horizontal margin', 'mx', $breakpoints, $neg_margins);
		$addSet($bs5, 'BS5 Spacing: Set negative vertical margin', 'my', $breakpoints, $neg_margins);


		// --- Sizing ---
		// Width
		$bs5[] = ['names' => 'w-25 w-50 w-75 w-100 w-auto', 'desc'  => 'BS5 Sizing: Set width relative to parent (%).'];
		$bs5[] = ['names' => 'mw-100', 'desc'  => 'BS5 Sizing: Set max-width to 100%.'];
		$bs5[] = ['names' => 'vw-100', 'desc'  => 'BS5 Sizing: Set width to 100% of viewport width.'];
		$bs5[] = ['names' => 'min-vw-100', 'desc'  => 'BS5 Sizing: Set min-width to 100% of viewport width.'];

		// Height
		$bs5[] = ['names' => 'h-25 h-50 h-75 h-100 h-auto', 'desc'  => 'BS5 Sizing: Set height relative to parent (%).'];
		$bs5[] = ['names' => 'mh-100', 'desc'  => 'BS5 Sizing: Set max-height to 100%.'];
		$bs5[] = ['names' => 'vh-100', 'desc'  => 'BS5 Sizing: Set height to 100% of viewport height.'];
		$bs5[] = ['names' => 'min-vh-100', 'desc'  => 'BS5 Sizing: Set min-height to 100% of viewport height.'];


		// --- Display & Visibility ---
		// Display Property
		$addSet($bs5, 'BS5 Display: Change the display property (e.g., d-none to hide)', 'd', $breakpoints, ['none', 'inline', 'inline-block', 'block', 'grid', 'table', 'table-row', 'table-cell', 'flex', 'inline-flex']);

		// Visibility Property
		$bs5[] = ['names'	=> 'visible invisible', 'desc'	=> 'BS5 Visibility: Control visibility without changing display. Invisible elements still take up space.'];

		// Screen Readers (Accessibility)
		$bs5[] = ['names'	=> 'visually-hidden', 'desc'	=> 'BS5 Accessibility: Hide elements visually but keep accessible to screen readers.'];
		$bs5[] = ['names'	=> 'visually-hidden-focusable', 'desc'	=> 'BS5 Accessibility: Combine with visually-hidden; shows element only when focused.'];


		// --- Flexbox --- (Apply to flex containers e.g., sections with `d-flex`)
		$addSet($bs5, 'BS5 Flex: Set flex direction', 'flex', $breakpoints, ['row', 'column', 'row-reverse', 'column-reverse']);
		$addSet($bs5, 'BS5 Flex: Justify content along main axis', 'justify-content', $breakpoints, ['start', 'end', 'center', 'between', 'around', 'evenly']);
		$addSet($bs5, 'BS5 Flex: Align items along cross axis', 'align-items', $breakpoints, ['start', 'end', 'center', 'baseline', 'stretch']);
		$addSet($bs5, 'BS5 Flex: Align self (on flex item) along cross axis', 'align-self', $breakpoints, ['start', 'end', 'center', 'baseline', 'stretch', 'auto']);
		$addSet($bs5, 'BS5 Flex: Control wrapping of flex items', 'flex', $breakpoints, ['wrap', 'nowrap', 'wrap-reverse']);
		$addSet($bs5, 'BS5 Flex: Control grow behavior of flex item', 'flex', $breakpoints, ['grow-0', 'grow-1']);
		$addSet($bs5, 'BS5 Flex: Control shrink behavior of flex item', 'flex', $breakpoints, ['shrink-0', 'shrink-1']);
		$addSet($bs5, 'BS5 Flex: Fill available space (apply to flex items)', 'flex', $breakpoints, ['fill']);
		$addSet($bs5, 'BS5 Flex: Change visual order of flex items', 'order', $breakpoints, $orders);


		// --- Positioning ---
		$bs5[] = ['names'	=> 'position-static position-relative position-absolute position-fixed position-sticky', 'desc'	=> 'BS5 Position: Set the element\'s position type.'];
		$bs5[] = ['names'	=> 'top-0 top-50 top-100 bottom-0 bottom-50 bottom-100 start-0 start-50 start-100 end-0 end-50 end-100', 'desc'	=> 'BS5 Position: Position element edges (requires non-static position). Start/End are LTR/RTL aware.'];
		$bs5[] = ['names'	=> 'translate-middle translate-middle-x translate-middle-y', 'desc'	=> 'BS5 Position: Center element using transforms (use with edge utilities like top-50/start-50).'];
		$bs5[] = ['names'	=> 'fixed-top fixed-bottom sticky-top sticky-bottom', 'desc'	=> 'BS5 Position: Shorthand for fixed/sticky positioning at top or bottom of viewport. `sticky-bottom` added in 5.2.',];
		// Responsive sticky top added in 5.2
		$addSet($bs5, 'BS5 Position: Responsive sticky top positioning', 'sticky', $breakpoints, ['top']);


		// --- Borders (Including 5.3 additions) ---
		$bs5[] = ['names'	=> 'border border-top border-end border-bottom border-start', 'desc'	=> 'BS5 Borders: Add borders to specific sides (respects LTR/RTL).'];
		$bs5[] = ['names'	=> 'border-0 border-top-0 border-end-0 border-bottom-0 border-start-0', 'desc'	=> 'BS5 Borders: Remove borders from specific sides.'];
		$bs5[] = ['names'	=> implode(' ', array_map(fn($c) => "border-$c", $all_border_colors)), 'desc'	=> 'BS5 Borders: Set border color using theme, subtle, white/black colors. Subtle colors adapt to light/dark modes.',];
		$bs5[] = ['names'	=> 'border-opacity-10 border-opacity-25 border-opacity-50 border-opacity-75 border-opacity-100', 'desc'	=> 'BS5 Borders: Set border color opacity.',];
		$bs5[] = ['names'	=> implode(' ', array_map(fn($w) => "border-$w", $border_widths)), 'desc'	=> 'BS5 Borders: Set border width (0-5).',];

		// Border Radius (Including 5.3 additions)
		$bs5[] = ['names'	=> 'rounded rounded-top rounded-end rounded-bottom rounded-start rounded-circle rounded-pill', 'desc'	=> 'BS5 Borders: Apply border radius to corners (specific sides respect LTR/RTL, circle, pill).'];
		$bs5[] = ['names'	=> implode(' ', array_map(fn($s) => "rounded-$s", $rounded_sizes)), 'desc'	=> 'BS5 Borders: Apply specific border radius sizes (0=none, 1-5=increasingly round). `rounded-4`, `rounded-5` added 5.3.',];


		// --- Shadows ---
		$bs5[] = ['names'	=> 'shadow-none shadow-sm shadow shadow-lg', 'desc'	=> 'BS5 Shadows: Add or remove box shadows.',];


		// --- Components (Examples suitable for sections) ---
		// Alerts
		$bs5[] = ['names'	=> 'alert', 'desc'	=> 'BS5 Alert: Base class for alert message boxes. Use with alert-* color classes or text-bg-* helpers.',];
		$bs5[] = ['names' => implode(' ', array_map(fn($c) => "alert-$c", $colors)), 'desc'  => 'BS5 Alert Colors: Contextual alert styles (traditional method).',];
		$bs5[] = ['names'	=> 'alert-heading', 'desc'	=> 'BS5 Alert: Style heading text within an alert.',];
		$bs5[] = ['names'	=> 'alert-link', 'desc'	=> 'BS5 Alert: Style links within an alert to match the color.',];
		$bs5[] = ['names'	=> 'alert-dismissible', 'desc'	=> 'BS5 Alert: Add for a closable alert (requires JS & button).',];

		// Cards
		$bs5[] = ['names'	=> 'card', 'desc'	=> 'BS5 Card: Base class for card component wrapper section.',];
		$bs5[] = ['names'	=> 'card-header card-body card-footer', 'desc'	=> 'BS5 Card Content: Use for child sections inside a `card` section for structure.',];
		$bs5[] = ['names'	=> 'card-img card-img-top card-img-bottom', 'desc'	=> 'BS5 Card Image: Use for image sections within a card.',];
		$bs5[] = ['names'	=> 'card-img-overlay', 'desc'	=> 'BS5 Card Overlay: Use for content section placed *after* `card-img` to overlay text.',];
		$bs5[] = ['names'	=> 'card-title card-subtitle card-text card-link', 'desc'	=> 'BS5 Card Typography: Use for text/link elements inside card content sections.',];
		$bs5[] = ['names'	=> 'card-group', 'desc'	=> 'BS5 Card Layout: Group cards without gutters.',];
		// Note: card-columns removed (use Masonry JS), card-deck removed (use grid).

		// Offcanvas (Responsive added in 5.2)
		$bs5[] = ['names' => 'offcanvas offcanvas-start offcanvas-end offcanvas-top offcanvas-bottom', 'desc' => 'BS5 Offcanvas: Base classes for the offcanvas panel itself (place outside main content). Position determines slide direction.'];
		$addSet($bs5, 'BS5 Offcanvas: Make offcanvas responsive, showing permanently above breakpoint', 'offcanvas', $breakpoints, ['']); // Generates offcanvas-sm, offcanvas-md etc. Applied to the .offcanvas element.


		// --- Utilities ---

		// Float (Use flexbox/grid where possible)
		$addSet($bs5, 'BS5 Float: Float element start/end (right/left in LTR). Avoid if possible.', 'float', $breakpoints, ['start', 'end', 'none']);

		// Overflow
		$bs5[] = ['names'	=> 'overflow-auto overflow-hidden overflow-visible overflow-scroll', 'desc'	=> 'BS5 Overflow: Control how content overflows the element\'s box.',];
		$addSet($bs5, 'BS5 Overflow: Control horizontal overflow', 'overflow-x', [''], ['auto', 'hidden', 'visible', 'scroll']);
		$addSet($bs5, 'BS5 Overflow: Control vertical overflow', 'overflow-y', [''], ['auto', 'hidden', 'visible', 'scroll']);


		// Interactions
		$bs5[] = ['names' => 'user-select-all user-select-auto user-select-none', 'desc'  => 'BS5 Interaction: Control text selection behavior.'];
		$bs5[] = ['names' => 'pe-none pe-auto', 'desc'  => 'BS5 Interaction: Control pointer events (e.g., make element unclickable).'];

		// Focus Ring (New in 5.2, enhanced 5.3)
		$bs5[] = ['names' => 'focus-ring', 'desc' => 'BS5 Interaction Helper: Apply custom focus ring styles (often via CSS vars). Can be used with color helpers like `focus-ring-primary`.'];
		$bs5[] = ['names' => implode(' ', array_map(fn($c) => "focus-ring-$c", $colors)), 'desc'  => 'BS5 Interaction Helper: Set focus ring color. Requires `focus-ring` base class.',];


		// Icon Link (New in 5.2)
		$bs5[] = ['names' => 'icon-link', 'desc' => 'BS5 Helper: Style links with accompanying SVG icons (requires specific HTML structure).'];
		$bs5[] = ['names' => 'icon-link-hover', 'desc' => 'BS5 Helper: Apply icon link transform effect on hover.'];

		// Ratios
		$bs5[] = ['names' => 'ratio ratio-1x1 ratio-4x3 ratio-16x9 ratio-21x9', 'desc' => 'BS5 Ratios: Create responsive aspect ratios for embeddable content (video, maps). Apply to parent element.'];

		// Vertical Rule <hr> Helper
		$bs5[] = ['names' => 'vr', 'desc' => 'BS5 Helper: Create vertical dividers, typically used within flex layouts.'];

		// Print Utilities
		$bs5[] = ['names'	=> 'd-print-none d-print-inline d-print-inline-block d-print-block d-print-grid d-print-table d-print-table-row d-print-table-cell d-print-flex d-print-inline-flex', 'desc'	=> 'BS5 Print: Change element display property only when printing.',];

		return $bs5;
	}


	/**
	 * Display form for selecting classes
	 *
	 */
	private function ClassesForm(){
		global $dataDir, $langmessage;

		echo '<h2 class="hmargin">' . $langmessage['Manage Classes'] . '</h2>';

		$cmd = \gp\tool::GetCommand();
		switch($cmd){
			case 'LoadDefault':
				$loaded_classes = self::Defaults();
			break;

			case 'LoadBootstrap3':
				$loaded_classes = self::Bootstrap3();
			break;

			case 'LoadBootstrap4':
				$loaded_classes = self::Bootstrap4();
			break;
			
			case 'LoadBootstrap5':
				$loaded_classes = self::Bootstrap5();
			break;

			default:
				$loaded_classes = self::GetClasses();
			break;
		}

		$classes = self::GetClasses();

		$processing = !empty($_REQUEST['process']) ? $_REQUEST['process'] : 'load';

		switch($processing){
			case 'prepend':
				$classes = array_unique(array_merge($loaded_classes, $classes), SORT_REGULAR);
			break;

			case 'append':
				$classes = array_unique(array_merge($classes, $loaded_classes), SORT_REGULAR);
			break;

			case 'remove':
				$classes = array_udiff($classes, $loaded_classes, function($a, $b){
					return strcmp($a['names'], $b['names']);
				});
			break;

			case 'load':
				$classes = $loaded_classes;
			break;
		}

		// the following is not beautiful ;)
		if( $cmd && $cmd != 'SaveClasses'){
			msg('OK. <a style="cursor:pointer;" '
				. 'onclick="$(\'button[value=SaveClasses]\').trigger(\'click\')">'
				. $langmessage['save'] . '</a> (?)');
		}

		$classes[] = array('names'=>'','desc'=>'');


		$this->page->jQueryCode .= '$(".sortable_table").sortable({items : "tr",handle: "td"});';

		// FORM
		echo '<form action="' . $this->admin_link . '" method="post">';
		echo '<table class="bordered full_width sortable_table manage_classes_table">';
		echo '<thead><tr><th style="width:50%;">' . $langmessage['Classes'] . '</th><th>' . $langmessage['description'] . '</th></tr></thead>';
		echo '<tbody>';

		foreach( $classes as $key => $classArray ){
			echo '<tr><td class="manage_class_name">';
			echo '<img alt="" src="'.\gp\tool::GetDir('/include/imgs/drag_handle.gif').'" /> &nbsp; ';
			echo '<input size="32" class="gpinput" type="text" name="class_names[]" value="' . htmlspecialchars($classArray['names'],ENT_COMPAT,'UTF-8') . '"/>';
			echo '</td><td class="manage_class_desc">';
			echo '<input size="64" class="gpinput" type="text" name="class_desc[]" value="' . htmlspecialchars($classArray['desc'],ENT_COMPAT,'UTF-8') . '"/> ';
			echo '<a class="gpbutton rm_table_row" title="Remove Item" data-cmd="rm_table_row"><i class="fa fa-trash"></i></a>';
			echo '</td></tr>';
		}

		echo '<tr><td colspan="3">';
		echo '<a data-cmd="add_table_row">' . $langmessage['add'] . '</a>';
		echo '</td></tr>';
		echo '</tbody>';
		echo '</table>';

		// SAVE / CANCEL BUTTONS
		echo '<br/>';
		echo '<button type="submit" name="cmd" value="SaveClasses" class="gpsubmit">'.$langmessage['save'].'</button>';
		echo '<button type="submit" name="cmd" value="" class="gpcancel">'.$langmessage['cancel'].'</button>';

		echo '</form>';

		echo '<div class="classes-load-presets well">';
		echo $langmessage['Manage Classes Description'];
		echo '<form action="' . $this->admin_link . '" method="get">';

		echo	'<h4>' . $langmessage['Load'] . ', ' . $langmessage['Merge'] . ', ' . $langmessage['remove'] . '</h4>';

		echo	'<select class="gpselect" name="cmd">';
		echo		'<option value="LoadDefault">'		. $langmessage['The Default Preset'] . '</option> ';
		echo		'<option value="LoadBootstrap3">'	. sprintf($langmessage['The Bootstrap Preset'], '3') . '</option> ';
		echo		'<option value="LoadBootstrap4">'	. sprintf($langmessage['The Bootstrap Preset'], '4') . '</option> ';
		            '<option value="LoadBootstrap5">'	. sprintf($langmessage['The Bootstrap Preset'], '5') . '</option> ';
		echo	'</select>';

		echo	'<button type="submit" name="process" value="load" class="gpsubmit">' . $langmessage['Load'] . '</button>';
		echo	'<button type="submit" name="process" value="prepend" class="gpsubmit">' . $langmessage['Prepend'] . '</button>';
		echo	'<button type="submit" name="process" value="append" class="gpsubmit">'  . $langmessage['Append'] . '</button>';
		echo	'<button type="submit" name="process" value="remove" class="gpsubmit">'  . $langmessage['remove'] . '</button>';

		echo '</form>';
		echo '</div>';

	}


	/**
	 * Save the posted data
	 *
	 */
	public function SaveClasses(){
		global $langmessage;

		$classes = array();
		foreach($_POST['class_names'] as $i => $class_names){

			$class_names = trim($class_names);
			if( empty($class_names) ){
				continue;
			}

			$classes[] = array(
				'names'		=> $class_names,
				'desc' 		=> $_POST['class_desc'][$i],
			);
		}


		if( \gp\tool\Files::SaveData('_config/classes','classes',$classes) ){
			msg($langmessage['SAVED']);
		}else{
			msg($langmessage['OOPS'].' (Not Saved)');
		}
	}


}
