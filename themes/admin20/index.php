<?php
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");
header('Cache-Control: no-store, no-cache, must-revalidate');
header("Pragma: no-cache");
?>
<?php //include_once APPPATH.'/config/site_menus.php'; ?>
<?php
//build a list of links for available languages
$languages=$this->config->item("supported_languages");

$lang_list='';
if ($languages!==FALSE)
{
	if (count($languages)>1)
	{
		foreach($languages as $language)
		{
			$lang_list.='| <span> '.anchor('switch_language/'.$language.'/?destination=admin', strtoupper($language)).' </span>';
		}
	}
}

$this->load->helper('site_menu');
$site_navigation_menu=get_site_menu();
?>
<!DOCTYPE html>
<html>
  <head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
	<base href="<?php echo js_base_url(); ?>">
	<title><?php echo $title; ?></title>
	
    <!-- style reset using YUI -->
    <!--[if lt IE 9]>
      <link rel="stylesheet" type="text/css" href="themes/admin/reset-fonts-grids.css">
    <![endif]-->
    
    <!-- Bootstrap -->
    <link href="themes/<?php echo $this->template->theme();?>/css/custom/bootstrap.css" rel="stylesheet">
	<link rel="stylesheet" type="text/css" href="themes/<?php echo $this->template->theme();?>/forms.css">
	<link rel="stylesheet" type="text/css" href="themes/<?php echo $this->template->theme();?>/catalog_admin.css">
    
    <!--[if lt IE 8]>
      <style>
      .btn-group > .btn-mini + .dropdown-toggle{border:0px solid red;padding:4px;vertical-align:top}
      </style>
    <![endif]-->
    
    

    <style type="text/css">
      body {
        padding-top: 60px;
        padding-bottom: 40px;
		text-align:left;
      }
      .sidebar-nav {
        padding: 9px 0;
      }
	  .sub-header{
	  	background: #F1F1F1;
		background: -webkit-gradient(radial,100 36,0,100 -40,120,from(#FAFAFA),to(#F1F1F1)),#F1F1F1;
		border-bottom: 1px solid #666;
		border-color: #E5E5E5;
		height: 85px;
		width: 100%;
		margin-top: -20px;
		margin-bottom: 20px;
	  }
	  
    </style>
    
    <!-- Le HTML5 shim, for IE6-8 support of HTML5 elements -->
    <!--[if lt IE 9]>
      <script src="http://html5shim.googlecode.com/svn/trunk/html5.js"></script>
    <![endif]-->

	<script src="themes/<?php echo $this->template->theme();?>/js/jquery-1.9.0.js"></script>
    <script src="themes/<?php echo $this->template->theme();?>/js/bootstrap.min.js"></script>
    <script src="javascript/jquery-migrate-1.0.0.min.js"></script>
        
    <script type="text/javascript"> 
   		var CI = {'base_url': '<?php echo site_url(); ?>'}; 
	</script> 

	<?php if (isset($_styles) ){ echo $_styles;} ?>
    <?php if (isset($_scripts) ){ echo $_scripts;} ?>

	<script type="text/javascript">
	$(document).ready(function()  {
		/*global ajax error handler */
		$( document ).ajaxError(function(event, jqxhr, settings, exception) {
			if(jqxhr.status==401){
				window.location=CI.base_url+'/auth/login/?destination=admin/';
			}
		});
	});
	</script>

  </head>
  <body>
  
<div class="navbar navbar-inverse navbar-fixed-top">
<div class="navbar-inner">
        <div class="container-fluid">
          <a class="btn btn-navbar" data-toggle="collapse" data-target=".subnav-collapse">
            <span class="icon-bar"></span>
            <span class="icon-bar"></span>
            <span class="icon-bar"></span>
          </a>
          <a class="brand" href="<?php echo site_url();?>/admin">NADA <?php echo APP_VERSION;?></a>
          <div class="nav-collapse subnav-collapse">
          <?php echo $site_navigation_menu;?>
          
          <ul class="nav pull-right">
              <li class="divider-vertical"></li>
              <li class="dropdown">
              <?php $user=strtoupper($this->session->userdata('username'));?>
			  <?php if ($user):?>
                <a href="#" class="dropdown-toggle" data-toggle="dropdown"><?php echo $user;?> <b class="caret"></b></a>
                <ul class="dropdown-menu">
                	<?php if ($this->session->userdata('impersonate_user')):?>
                  		<li><?php echo anchor('admin/users/exit_impersonate',t('exit_impersonate'));?></li>  
                    <?php endif;?>
                  <li><?php echo anchor('auth/change_password',t('change_password'));?></li>
                  <li><?php echo anchor('auth/logout',t('logout'));?></li>
                  <li class="divider"></li>
                  <li><a target="_blank" href="<?php echo site_url();?>"/><?php echo t('home');?></a></li>
                  <li><a  target="_blank" href="<?php echo site_url();?>/catalog"/><?php echo t('data_catalog');?></a></li>
                  <li><a  target="_blank" href="<?php echo site_url();?>/citations"/><?php echo t('citations');?></a></li>
                </ul>
                <?php endif;?>
              </li>
            </ul>
        </div>
      </div>  
</div>

</div>

<?php if(isset($collection)):?>
<div class="sub-header" > <?php echo $collection;?></div>
<?php endif;?>
    
    <div class="container-fluid">
        <div class="row-fluid">
             
             <!--breadcrumbs -->
			<?php $breadcrumbs_str= $this->breadcrumb->to_string();?>
            <?php if ($breadcrumbs_str!=''):?>
                <div id="breadcrumb" class="notabs">
                <?php echo $breadcrumbs_str;?>
                </div>
            <?php endif;?>
                
            <div id="content">
            <?php if (isset($content) ):?>
                <?php print $content; ?>
            <?php endif;?>
            </div> 
        
        </div>
    </div>    
    
  </body>
</html>