<h1 class="xsl-title"><?php echo t('search_data_dictionary');?></h1>
<?php $url=site_url().'/ddibrowser/'.$this->uri->segment(2).'/search'; ?>
<form action="" id="form_vsearch">
	<input type="text" name="vk" value="<?php echo form_prep($this->input->get('vk')); ?>" size="60" maxlength="100"/>
    <input type="submit" value="<?php echo t('search');?>" name="search" onclick="vsearch('<?php echo $url;?>/ajax/');return false;"/>    
    <?php if ($this->input->get("vk")):?>
    <a href="<?php echo current_url();?>"><?php echo t('reset');?></a>
    <?php endif;?>
    <?php /*
    <div>
    <input type="hidden" name="vf[]" id="name" value="name" checked="checked"/>
    <input type="hidden" name="vf[]" id="label" value="labl" checked="checked"/>
    <input type="hidden" name="vf[]" id="question" value="qstn"  checked="checked"/>
    <input type="hidden" name="vf[]" id="categories" value="catgry"  checked="checked"/>
    </div>  
	*/ ?>          

</form>
<div id="variable-list"></div>