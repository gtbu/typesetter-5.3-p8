  <?php
 
 /**
  * Typesetter CMS theme template
 */
 
global $page, $config;
$path = $page->theme_dir . '/drop_down_menu.php';
//include_once($path);
common::LoadComponents( 'bootstrap5.3-js','bootstrap-icons' ); 
$lang = isset($page->lang) ? $page->lang : $config['language'];

/**
 * If you are using Multi-Language Manager 1.2.3+
 * and want to use localized $langmessage values in the template,
 * uncomment the following line
 */
 // common::GetLangFile('main.inc', $lang);

?><!DOCTYPE html>
<html lang="<?php echo $lang; ?>" class="bootstrap-5">
  <head>
    <meta charset="utf-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />

    <?php
	  global $page, $config;
      gpOutput::GetHead();	  
	  common::LoadComponents( 'bootstrap-icons' );
	  $lang = isset($page->lang) ? $page->lang : $config['language'];
    ?>
  </head>
  
  
  <body>
    <!--[if lte IE 9]>
      <div class="alert alert-warning">
        <h3>Bootstrap 4</h3>
        <p>We&rsquo;re sorry but Internet Explorer 9 support was dropped as of Bootstrap version 4.</p>
      </div>
    <![endif]-->
    <header class="section-header row text-center">
           <div class="container col p-2 hd1">
		      <?php echo common::Link('', $config['title'], '', 'class="navbar-brand"');  ?>
           </div>	
           <div class="container col hd2"> 		  
             <?php gpOutput::Get('Extra', 'headx_b53_1'); ?>
           </div>			
    </header> <!-- section-header.// -->

      
        <nav class="navbar navbar-expand-lg text-body nav-custom shadow-sm sticky-top main-navigation">
            <div class="container-fluid">			
			            
                <button class="navbar-toggler" type="button" data-bs-toggle="offcanvas" data-bs-target="#offcanvasGp" aria-expanded="false" 
                    aria-label="Toggle navigation" aria-controls = 'offcanvasEx'>
                    <span class="navbar-toggler-icon"></span>
                </button>
			
            <div class = 'offcanvas offcanvas-start-lg bg-custom' tabindex = '-1' id = 'offcanvasGp' aria-labelledby = 'offcanvasLabel'>			

               <div class = 'offcanvas-header d-flex d-lg-none'>
                  <h5 class = 'offcanvas-title text-white' id = 'offcanvasLabel'>Navbar</h5>
                 <a href='#' class = 'text-reset p-0' data-bs-dismiss = 'offcanvas' aria-label = 'close'> 
	        	<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-x-lg" viewBox="0 0 16 16"><style>svg{fill:white}</style>
                <path d="M2.146 2.854a.5.5 0 1 1 .708-.708L8 7.293l5.146-5.147a.5.5 0 0 1 .708.708L8.707 8l5.147 5.146a.5.5 0 0 1-.708.708L8 8.707l-5.146 5.147a.5.5 0 0 1-.708-.708L7.293 8 2.146 2.854Z"/>
                </svg>  
               </a>  <!-- &#xF659;  &#128473; &#10005; &#10006; -->  
               </div>

                <div class="offcanvas-body sidebar pb-2 pb-lg-0" id="main_nav">
            <?php
              $GP_ARRANGE = false;
			  $GP_MENU_ELEMENTS = '';
              // main nav classes
              $GP_MENU_CLASSES = array(
                'menu_top'          => 'navbar-nav ms-lg-auto mb-2 mb-lg-0 col-sm-9',
				'a'					=> 'xy',
                'selected'          => 'active',
                'selected_li'       => '',
                'childselected'     => 'active',
                'childselected_li'  => 'active', // use '' if you do not want 1st-level nav items to indicate that a child item is active
                'li_'               => 'nav-item b1 nav-item-',
                'li_title'          => '',
                'haschildren'       => 'nav-link dropdown-toggle b2',  //a 
                'haschildren_li'    => 'dropdown b3',
                'child_ul'          => 'dropdown-menu shadow-sm b4',
              );

              gpOutput::Get('FullMenu'); //top two levels
            ?>
			
        	</div>
           </div> <!-- navbar-collapse.// -->
		   
		   
		   	   
		   <div id="search" class="col-md-3 justify-content-end float-right">		  
		                       <?php		//search form
						global $langmessage;
						$_GET += array('q'=>'');
					?>
						
				<form action="<?php echo common::GetUrl( 'special_gpsearch') ?>" method="get" class="fmail">
				     <div class="input-group">
					<input name="q" type="text" class="form-control" value="<?php echo htmlspecialchars($_GET['q']) ?>" 
						placeholder="<?php echo $langmessage['Search'] ?>">
					            
                            <span class="input-group-btn"><button type="submit" class="btn btn-custom" type="button">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" 
								 class="bi bi-search" viewBox="0 0 16 16">
                                 <path d="M11.742 10.344a6.5 6.5 0 1 0-1.397 1.398h-.001c.03.04.062.078.098.115l3.85 3.85a1 1 0 0 0 1.415-1.414l-3.85-3.85a1.007 1.007 0 0 0-.115-.1zM12 6.5a5.5 5.5 0 1 1-11 0 5.5 5.5 0 0 1 11 0z"/>
                                 </svg></button>
                            </span>
							
				    </div> 						
				</form>
		    </div>	<!-- search -->
			
			
			
          </div> <!-- container-fluid.// -->
        </nav>
		
		
		
   <!--  </div> container //  -->

  <main class="pt-1">   
      <section class="container">               
        <?php $page->GetContent(); ?>    	  
      </section>
  </main><!-- /.main-content -->

    <footer class="main-footer bottom-0 mt-auto p-2">
      <div class="container">

        <div class="row">
          <div class="col-sm-6 col-lg-4 footer-column footer-column-1">
            <?php gpOutput::Get('Extra', 'bt5.3_offcan_FootCol_1a'); ?>
          </div>

          <div class="col-sm-6 col-lg-4 footer-column footer-column-2">
            <?php gpOutput::Get('Extra', 'bt5.3_offcan_FootCol_2a'); ?>
          </div>

          <div class="col-sm-6 col-lg-4  footer-column footer-admin-links">
		    <p>   &copy; <?php  echo date("Y"); ?> <?php echo $_SERVER["SERVER_NAME"]; ?> </p>
			   &nbsp; &#x20;
			   <svg xmlns="http://www.w3.org/2000/svg" height="1em" viewBox="0 0 576 512"><!--! Font Awesome Free 6.4.0 by @fontawesome - https://fontawesome.com License - https://fontawesome.com/license (Commercial License) Copyright 2023 Fonticons, Inc. -->
			   <style>svg{fill:#585858}</style><path d="M208 80c0-26.5 21.5-48 48-48h64c26.5 0 48 21.5 48 48v64c0 26.5-21.5 48-48 48h-8v40H464c30.9 0 56 25.1 56 56v32h8c26.5 0 48 21.5 48 48v64c0 26.5-21.5 48-48 48H464c-26.5 0-48-21.5-48-48V368c0-26.5 21.5-48 48-48h8V288c0-4.4-3.6-8-8-8H312v40h8c26.5 0 48 21.5 48 48v64c0 26.5-21.5 48-48 48H256c-26.5 0-48-21.5-48-48V368c0-26.5 21.5-48 48-48h8V280H112c-4.4 0-8 3.6-8 8v32h8c26.5 0 48 21.5 48 48v64c0 26.5-21.5 48-48 48H48c-26.5 0-48-21.5-48-48V368c0-26.5 21.5-48 48-48h8V288c0-30.9 25.1-56 56-56H264V192h-8c-26.5 0-48-21.5-48-48V80z"/></svg>
		 		   
			   <?php gpOutput::GetAdminLink(); ?>  
          </div>
        </div><!-- /.row -->

      </div><!-- /.container -->
    </footer><!-- /.main-footer -->
  </body>
</html>
