<?php
//form action url
$uri_arr=$this->uri->segment_array();
$form_action_url=site_url().'/admin/regions';
$row_id=$this->uri->segment(4);
if (is_numeric($row_id))
{
	$form_action_url.='/edit/'.$row_id;
	
	//remove region from the parent list when editing the same region
	if (array_key_exists($row_id,$parent_regions))
	{
		unset($parent_regions[$row_id]);
	}
}
else
{
	$form_action_url.='/add/';
}

$countries=get_form_value('country',isset($row['countries']) ? $row['countries']: array('') );
?>


<style type="text/css">
.selected-container{font-weight:normal;font-size:12px;}
.input-fixed-1{width:300px;}
.country-selection{height:200px;overflow:auto;width:300px;border:1px solid gainsboro;}
.form .normal label{margin:0px;padding:0px;display:inline;font-weight:normal;}
.clear-all{color:navy;font-weight:normal;cursor:pointer;margin-left:10px;}
</style>
<div class='content-container'>
    <div class="page-links">
        <a href="<?php echo site_url(); ?>/admin/regions" class="button"><img src="images/house.png"/><?php echo t('home');?></a>
    </div>
<?php if ($row_id):?>
	<h1><?php echo t('Edit Region'); ?></h1>
<?php else:?>
	<h1><?php echo t('Create New Region'); ?></h1>
<?php endif;?>    
	<?php if (validation_errors() ) : ?>
        <div class="error">
            <?php echo validation_errors(); ?>
        </div>
    <?php endif; ?>
    
    <?php $error=$this->session->flashdata('error');?>
    <?php echo ($error!="") ? '<div class="error">'.$error.'</div>' : '';?>
        
    <?php $message=$this->session->flashdata('message');?>
    <?php echo ($message!="") ? '<div class="success">'.$message.'</div>' : '';?>

	
    <?php echo form_open($form_action_url, array('class'=>'form'));?>

    
    <div class="field">
        <label for="pid"><?php echo t('Select Parent');?><span class="required">*</span></label>
        <?php echo form_dropdown('pid', $parent_regions, get_form_value("pid",isset($row['pid']) ? $row['pid'] : ''), 'id="pid"'); ?>
    </div>
    
    <div class="field">
        <label for="title"><?php echo t('name');?><span class="required">*</span></label>
        <input class="input-fixed-1" name="title" type="text" id="title"  value="<?php echo get_form_value('title',isset($row['title']) ? $row['title'] : ''); ?>"/>
    </div>
    
    <div class="field">
        <label for="weight"><?php echo t('Weight');?><span class="required">*</span></label>
        <input class="input-fixed-1" name="weight" type="text" id="weight"  value="<?php echo get_form_value('weight',isset($row['weight']) ? $row['weight'] : ''); ?>"/>
    </div>

    <div class="field" id="country-selection">
        <label for="pid"><?php echo t('Select Countries');?><span class="required">*</span> 
        	<span class"selected-container">
				<?php echo t('selected');?>: <span class="selected"><?php echo count($countries);?></span>
                <span class="clear-all"><?php echo t('clear');?></span>
            </span>
        </label>
        <div class="country-selection">
        <?php foreach($country_list as $country):?>
        <?php $is_checked=in_array($country['countryid'],$countries) ? 'checked="checked"' : '';?>
        <div class="country-row normal">
            <label for="c-<?php echo $country['countryid'];?>">
            <input class="chk-country" type="checkbox" name="country[]" id="c-<?php echo $country['countryid'];?>"  value="<?php echo $country['countryid'];?>" <?php echo $is_checked;?> /><?php echo $country['name'];?>
            </label>
            <br/>
        </div>
        <?php endforeach;?>
        </div>
    </div>

	<?php echo form_submit('submit', t('update'));?>
    <?php echo anchor('admin/regions/', t('cancel'));?>
      
    <?php echo form_close();?>

</div>


<script type="application/javascript">
$(document).ready(function() 
{	
	$("#pid").change(function() {
		set_country_selection();
	});
	
	$(".country-selection .chk-country").click(function() {
		show_selected_stats();
	});
	
	$(".clear-all").click(function() {
		$(".country-selection .chk-country").prop("checked",false);
		show_selected_stats();
	});
	
	function show_selected_stats(){
		$("#country-selection .selected").html($(".country-selection .chk-country:checked").length);
	}
	
	
	
	function set_country_selection()
	{
		if ($("#pid").val()==0){
		$(".country-selection :input").prop("disabled",true);
		$("#country-selection").hide();
		}
		else{
		$(".country-selection :input").prop("disabled",false);
		$("#country-selection").show();
		}		
	}
	
	set_country_selection();
	show_selected_stats();
});

</script>