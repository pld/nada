<?php
class Catalog extends MY_Controller {

	//active repository object
	var $active_repo=NULL;
	
    public function __construct()
    {
        parent::__construct($skip_auth=TRUE);
		
       	$this->template->set_template('default');
		$this->template->write('sidebar', $this->_menu(),true);	
		
		$this->load->helper('pagination_helper');
		$this->load->model('Search_helper_model');
		$this->load->model('Catalog_model');
		$this->load->model('Vocabulary_model');
		$this->load->model('Repository_model');
		$this->load->model('Form_model');
	 	//$this->output->enable_profiler(TRUE);
    		
		//language files
		$this->lang->load('general');
		$this->lang->load('catalog_search');
		
		//configuration settings
		$this->limit= $this->get_page_size();
		$this->topic_search=($this->config->item("topic_search")===FALSE) ? 'no' : $this->config->item("topic_search");
		$this->regional_search=($this->config->item("regional_search")===FALSE) ? 'no' : $this->config->item("regional_search");
		$this->center_search=($this->config->item("center_search")===FALSE) ? 'no' : $this->config->item("center_search");
		$this->collection_search=($this->config->item("collection_search")===FALSE) ? 'no' : $this->config->item("collection_search");
		$this->da_search=($this->config->item("da_search")===FALSE) ? 'no' : $this->config->item("da_search");
				
		//set template for print
		if ($this->input->get("print")==='yes')
		{
			$this->template->set_template('blank');
		}
	}

	/**
	*
	* Return the page size from querystring, session, config
	*
	**/
	function get_page_size()
	{
		//default from the config
		$limit=($this->config->item("catalog_records_per_page")===FALSE) ? 15 : $this->config->item("catalog_records_per_page");
		
		//check from querystring
		$ps=(int)$this->input->get_post("ps");

		if (is_numeric($ps))
		{
			$limit=$ps;
		}
		
		//check cookie
		if(is_numeric($cookie_ps=$this->input->cookie('ps',TRUE)))
		{
			$limit= $cookie_ps;
		}

		if (!is_numeric($limit) || $limit<=0)
		{
			return 15;			
		}
		
		//set max size limit
		if($limit>500)
		{
			return 500;
		}
		
		return $limit;
	}
 
 

	function index()
	{		
		if ($this->input->get('ajax') )
		{
			$this->search();return;
		}
		
		//unpublished repos are visible to limited admins or admins only
		$this->acl->user_has_unpublished_repo_access_or_die(NULL,$this->active_repo['repositoryid']);
		
		$embed_js=$this->load->view('catalog_search/js_translations',NULL,TRUE);
		$this->template->add_js($embed_js,'embed');
		
		$this->template->add_js('javascript/datacatalog.js');
		$this->template->add_css('javascript/jquery/themes/base/jquery-ui.css');
		$this->template->add_js('javascript/jquery/ui/jquery.ui.core.js');
		$this->template->add_js('javascript/jquery/ui/jquery.ui.position.js');
		$this->template->add_js('javascript/jquery/ui/jquery.ui.widget.js');
		$this->template->add_js('javascript/jquery/ui/jquery.ui.button.js');
		$this->template->add_js('javascript/jquery/ui/jquery.ui.tabs.js');
		$this->template->add_js('javascript/jquery/ui/jquery.ui.dialog.js');
		$this->template->add_js('javascript/jquery.scrollTo-min.js');
		$this->template->add_js('javascript/jquery.blockui.js');
		$this->template->add_css('themes/'.$this->template->theme().'/datacatalog.css');
		
		//page description metatags
		$this->template->add_meta("description",t('meta_description_catalog'));

		//get list of all repositories
		//$this->repositories=$this->Catalog_model->get_repositories();
		
		$search_options=new StdClass;
		$data=array();
		
		$this->da_search='yes';
		
		//get list of DA types available for current repository
		if ($this->da_search)
		{
			$data['da_types']=$this->Search_helper_model->get_active_data_types($this->active_repo['repositoryid']);
		}
				
		//get year min/max
		$data['min_year']=$this->Search_helper_model->get_min_year();
		$data['max_year']=$this->Search_helper_model->get_max_year();
		
		if ($this->regional_search)
		{
			//get list of active countries
			$data['countries']=$this->Search_helper_model->get_active_countries($this->active_repo['repositoryid']);
		}

		$search_output= $this->_search();
		
		if ($search_output['search_type']=='variable')
		{
			$data['search_result']=$this->load->view('catalog_search/variable_list', $search_output,TRUE);
		}
		else
		{
			//get featured studies for the resultset
			$search_output['featured_studies']=$this->get_featured_study($search_output['surveys']['rows']);
			
			//display result using study view
			$data['search_result']=$this->load->view('catalog_search/survey_list', $search_output,TRUE);
		}

		//$data['search_result']=$this->_search();
		$data['active_repo']=$this->active_repo['repositoryid'];
		
		if($this->topic_search=='yes')
		{
			//get vocabulary id from config
			$vid=$this->config->item("topics_vocab");
		
			if ($vid!==FALSE && is_numeric($vid))
			{				
				$this->load->model('term_model');
				$data['topics']=$this->Vocabulary_model->get_terms_array($vid,$active_only=TRUE,$data['active_repo']);
				$data['topic_search']=TRUE;				
			}
			else
			{
				//hide the topics box
				$data['topic_search']='no';
			}
		}	
		
		//collection/repo filter
		$data['repositories']=$this->Repository_model->get_repositories_with_survey_counts();
		
		//get years
		$min_year=$this->Search_helper_model->get_min_year();
		$max_year=$this->Search_helper_model->get_max_year();

		foreach (range($max_year, $min_year) as $year) 
		{
        	$data['years'][$year]=$year;
        }

		//set page title
		if (isset($this->active_repo) && $this->active_repo!=='')
		{
			$this->page_title=t($this->active_repo['title']);
		}
		else
		{
			$this->page_title=t('central_data_catalog');
		}
		
		//show search form
		$this->template->write('search_filters', $this->load->view('catalog_search/catalog_facets', $data,true),true);
		
		$page_data=array(
			'repo'=>$this->active_repo,
			'active_tab'=>'catalog',
			'repo_citations_count'=>$this->repository_model->get_citations_count_by_collection($this->active_repo['repositoryid'])
		);

		$page_data['content']=$this->load->view('catalog_search/catalog_search_result', $data,true);
		
		//show page contents in tabs
		$content=$this->load->view("catalog_search/study_collection_tabs",$page_data,TRUE);

		//render final output
		$this->template->write('title', $this->page_title,true);
		$this->template->write('content', $content,true);
	  	$this->template->render();
	}


	
	
	function search()
	{
		$output= $this->_search();
		
		if ($output['search_type']=='variable')
		{
			$this->load->view('catalog_search/variable_list', $output);
		}
		else
		{			
			//get featured studies for the resultset
			$output['featured_studies']=$this->get_featured_study($output['surveys']['rows']);
			
			$this->load->view('catalog_search/survey_list', $output);
		}
	}
		
	
	function _search()
	{
		//all keys that needs to be persisted
		$get_keys_array=array('sort_order','sort_by','sk','vk','vf','from','to','country','view','topic','page','repo','center','collection');
		
		$this->load->helper('security');
		
		//get year min/max
		$data['min_year']=$this->Search_helper_model->get_min_year();
		$data['max_year']=$this->Search_helper_model->get_max_year();
		
		$search_options=new StdClass;
		$search_options->filter= new StdClass;
		
		//page parameters
		$search_options->collection		=xss_clean($this->input->get("collection"));
		$search_options->sk				=xss_clean($this->input->get("sk"));
		$search_options->vk				=xss_clean($this->input->get("vk"));
		$search_options->vf				=xss_clean($this->input->get("vf"));
		$search_options->country		=xss_clean($this->input->get("country"));
		$search_options->view			=xss_clean($this->input->get("view"));		
		$search_options->topic			=xss_clean($this->input->get("topic"));
		$search_options->from			=xss_clean($this->input->get("from"));
		$search_options->to				=xss_clean($this->input->get("to"));		
		$search_options->sort_by		=xss_clean($this->input->get("sort_by"));
		$search_options->sort_order		=xss_clean($this->input->get("sort_order"));
		$search_options->page			=(int)xss_clean($this->input->get("page"));
		$search_options->page			=($search_options->page >0) ? $search_options->page : 1;
		$search_options->filter->repo	=xss_clean($this->active_repo['repositoryid']);
		$search_options->dtype			=xss_clean($this->input->get("dtype"));		
		$offset=						($search_options->page-1)*$this->limit;

		//allowed fields for sort_by and sort_order 
		$allowed_fields = array('proddate','titl','labl','nation','popularity');
		$allowed_order=array('asc','desc');
		
		//set default sort options, if passed values are not valid
		if (!in_array(trim($search_options->sort_by),$allowed_fields))
		{
			$search_options->sort_by='';
		}
		
		//default for sort order if no valid values found
		if (!in_array($search_options->sort_order,$allowed_order))
		{
			$search_options->sort_order='';
		}

		//log
		$this->db_logger->write_log('search',$this->input->get("sk").'/'.$this->input->get("vk"),'sk-vk');

		//get list of all repositories
		$data['repositories']=$this->Catalog_model->get_repositories();

		if ($this->regional_search)
		{
			$data['countries']=$this->Search_helper_model->get_active_countries($this->active_repo['repositoryid']);
		}
		
		if($this->topic_search=='yes')
		{
			//get vocabulary id from config
			$vid=$this->config->item("topics_vocab");
		
			if ($vid!==FALSE && is_numeric($vid))
			{				
				//$this->load->model('Vocabulary_model');
				$this->load->model('term_model');
				
				//get topics by vid
				$data['topics']=$this->Vocabulary_model->get_terms_array($vid,$active_only=TRUE);//$this->Vocabulary_model->get_tree($vid);
				$data['topic_search']=TRUE;				
			}
			else
			{
				//hide the topics box
				$data['topic_search']='no';
			}
		}
		

		//which view to use for display	
		if ($search_options->vk!='' && $search_options->view=='v')
		{
			//variable search
			$params=array(
				//'center'=>$search_options->center,
				'collections'=>$search_options->collection,
				'study_keywords'=>$search_options->sk,
				'variable_keywords'=>$search_options->vk,
				'variable_fields'=>$search_options->vf,
				'countries'=>$search_options->country,
				'topics'=>$search_options->topic,
				'from'=>$search_options->from,
				'to'=>$search_options->to,
				'sort_by'=>$search_options->sort_by,
				'sort_order'=>$search_options->sort_order,
				'repo'=>$search_options->filter->repo,
				'dtype'=>$search_options->dtype
			);		

			$this->load->library('catalog_search',$params);
			$search_result=$this->catalog_search->vsearch($this->limit,$offset);

			$data=array_merge($search_result,$data);
			$data['current_page']=$search_options->page;
			$data['search_options']=$search_options;
			$data['data_access_types']=$this->Form_model->get_form_list();
			$data['search_type']='variable';
			return $data;
			
		}
		
		//$surveys=$this->Advanced_search_model->search($this->limit,$offset);		
		$params=array(
			//'center'=>$search_options->center,
			'collections'=>$search_options->collection,
			'study_keywords'=>$search_options->sk,
			'variable_keywords'=>$search_options->vk,
			'variable_fields'=>$search_options->vf,
			'countries'=>$search_options->country,
			'topics'=>$search_options->topic,
			'from'=>$search_options->from,
			'to'=>$search_options->to,
			'sort_by'=>$search_options->sort_by,
			'sort_order'=>$search_options->sort_order,
			'repo'=>$search_options->filter->repo,
			'dtype'=>$search_options->dtype
		);		
		
		$this->load->library('catalog_search',$params);
		$data['surveys']=$this->catalog_search->search($this->limit,$offset);
		$data['current_page']=$search_options->page;
		$data['search_options']=$search_options;
		$data['data_access_types']=$this->Form_model->get_form_list();
		$data['search_type']='study';
		return $data;
		//return $this->load->view('catalog_search/survey_list', $data);
		
		//$this->load->library("tracker");
		//$this->tracker->track();
	}


	
	/**
	* variable search
	*
	*/
	function vsearch($surveyid=NULL)
	{
		if ($surveyid==NULL || !is_numeric($surveyid))
		{
			echo t('error_invalid_parameters');
			return;
		}
		
		//$data['variables']=$this->Advanced_search_model->v_quick_search($surveyid);
		$params=array(
			'study_keywords'=>$this->input->get_post('sk'),
			'variable_keywords'=>$this->input->get_post('vk'),
			'variable_fields'=>$this->input->get_post('vf'),
			'countries'=>$this->input->get_post('country'),
			'topics'=>$this->input->get_post('topic'),
			'from'=>$this->input->get_post('from'),
			'to'=>$this->input->get_post('to'),
			'sort_by'=>$this->input->get_post('sort_by'),
			'sort_order'=>$this->input->get_post('sort_order'),
			'repo'=>$this->input->get_post('repo')
		);		
		$this->load->library('catalog_search',$params);
		$data['variables']=$this->catalog_search->v_quick_search($surveyid);

		$this->load->view("catalog_search/var_quick_list", $data);
	}

	
	/**
	*
	* Perform variable comparison
	*
	**/
	function compare($option=NULL, $format=NULL)
	{
		$items=explode(",",$this->input->cookie('variable-compare', TRUE));
		$list=array();
		
		if ($items)
		{
			foreach($items as $item=>$value)
			{
				$tmp=explode('/',$value);
				if (isset($tmp[1]))
				{
					$list[]=array('surveyid'=>$tmp[0], 'varid'=>$tmp[1]);
				}	
			}
		}
		
		$this->load->library('Compare_variable');
		$data['list']=$list;	
		if ($option=='print')
		{
			if ($format!=='pdf')
			{
				$this->load->view("catalog_search/compare_print",$data);exit;
			}
			else if ($format==='pdf')
			{
				$this->load->library('pdf_export');
				$contents=$this->load->view("catalog_search/compare_print",$data,TRUE);
				$this->pdf_export->create_pdf($contents);
				exit;
			}	
		}
				
		
		$this->template->set_template('blank');	
		$this->template->add_js('javascript/dragtable.js');
		$this->template->add_css('themes/ddibrowser/ddi.css');
		
		$content=$this->load->view("catalog_search/compare",$data,TRUE);
		$this->template->write('title', t('title_compare_variables'),true);
		$this->template->write('content', $content,true);
	  	$this->template->render();		
	}
	
	
	
	/**
	*
	* Returns a JSON data for filtering by country selection
	*
	**/
	function filter_by_country()
	{
		$countries=$this->security->xss_clean($this->input->get('country'));
		$year_from=(integer)$this->security->xss_clean($this->input->get('from'));
		$year_to=(integer)$this->security->xss_clean($this->input->get('to'));

		if (!is_array($countries))
		{
			exit;
		}
		
		$this->load->model('Search_helper_model');
		
		$data=$this->Search_helper_model->filter_by_countries($countries,$year_from, $year_to);
		echo json_encode($data);		
	}



	/**
	* Return JSON data to filter search box by topic
	*
	*/
	function filter_by_topic()
	{
		$topics=$this->input->get('topic');
		$min_year=(integer)$this->input->get('from');
		$max_year=(integer)$this->input->get('to');

		if (!is_array($topics))
		{
			exit;
		}

		$this->load->model('Search_helper_model');
		$data=$this->Search_helper_model->filter_by_topics($topics,$min_year,$max_year);		
		echo json_encode($data);
	}

	/**
	* Return JSON data to filter search box by topic
	*
	*/
	function filter_by_collection()
	{
		$collections=$this->input->get('collection');
		$min_year=(integer)$this->input->get('from');
		$max_year=(integer)$this->input->get('to');

		if (!is_array($topics))
		{
			exit;
		}

		$this->load->model('Search_helper_model');
		$data=$this->Search_helper_model->filter_by_collections($collections,$min_year,$max_year);		
		echo json_encode($data);
	}

	/**
	* return data to filter search box by year
	*
	*/
	function filter_by_years()
	{
		$min_year=$this->input->get('from');
		$max_year=$this->input->get('to');

		if (!is_numeric($min_year) || !is_numeric($max_year))
		{
			return false;
		}
		
		//get filtered list of countries and min/min years
		$data=$this->Search_helper_model->filter_by_years($min_year, $max_year);
		
		if (!isset($data["topics"]))
		{
			$data['topics']=array('NULL');
		}
		if (!isset($data["countries"]))
		{
			$data['countries']=array('NULL');
		}		
		
		echo json_encode($data);
	}
	
	
	
	/**
	* Search help page
	*
	*/
	function help()
	{
		if ($this->uri->segment(4)!==FALSE)
      {
        show_404();
      } 
	  
	  	$this->lang->load("search_help");
	   
      	echo t('keyword_search_help');exit;
	}
	

	/**
	* Data Catalog RSS feeds
	*
	* By default shows 50 latest surveys
	*
	* //TODO: 
	*	- get all records
	* 	- get all records as zip file
	* 	- get data by date ranges
	* 	- paginate?
	*/
	function rss()
	{	
		$limit=50;
		
		if (is_numeric($this->input->get('limit')))
		{
			$limit=$this->input->get('limit');
		}

		$data['records']=$this->Catalog_model->select($limit,$offset=0,$sort_by='changed',$sort_order='desc');	
        $contents=$this->load->view('catalog_search/rss', $data,TRUE);
		
		if ($this->input->get('format')=='zip')
		{
			$this->_rss_zip($contents);
		}
		else
		{
			header("Content-Type: application/xml");
			echo $contents;
		}
	}
	

	/**
	* Creates a zip file for data catalog rss
	*
	*/
	function _rss_zip($data)
	{
		$this->load->library('zip');
		
		$name = 'rss.txt';
		$this->zip->add_data($name, $data);

		//start file download
		$this->zip->download('rss.zip');
	}


	/**
	* Returns survey external resources (RDF) 
	*
	* 
	*/
	function rdf($id=NULL)
	{
		if (!is_numeric($id) )
		{
			show_404();return;
		}		
	
		$this->load->model('Catalog_model');
		//$this->Catalog_model->increment_study_download_count($id);
		
		header("Content-Type: application/xml");
		header('Content-Encoding: UTF-8');
		header( "Content-Disposition: attachment;filename=study-$id.rdf");

		echo $this->Catalog_model->get_survey_rdf($id);
	}
	
	
	/**
	* Returns survey DDI file
	* as .xml or .zip
	* 
	*/
	function ddi($id=NULL)
	{
		if (!is_numeric($id))
		{
			show_404();
		}
	
		$format=$this->input->get("format");
		
		//required for getting ddi file path
		$this->load->model('Catalog_model');
		$this->load->helper('download');
			
		//get ddi file path from db
		$ddi_file=$this->Catalog_model->get_survey_ddi_path($id);
		
		if ($ddi_file===FALSE)
		{
			show_404();
		}
		
		//$this->Catalog_model->increment_study_download_count($id);		

		if (file_exists($ddi_file))
		{
			if($format=='zip')
			{
				$this->load->library('zip');

				//zip file path
				$zip_file=$ddi_file.'.zip';
			
				//create zip if not created already
				if (!file_exists($zip_file))
				{			
					$this->zip->read_file($ddi_file);
					$this->zip->archive($zip_file); 
				}
				
				//download zip file
				if (file_exists($zip_file))
				{
					force_download2($zip_file);
					return;
				}
			}
			
			//download the xml file
			force_download2($ddi_file);
			return;
		}
		else
		{
			show_404();
		}		
	}
	
	function study($codebookid=NULL)
	{
		if ($codebookid==NULL)
		{
			show_404();
		}
		
		$survey=$this->Catalog_model->select_single($codebookid);
		
		if ($survey)
		{
			redirect('catalog/'.$survey['id']);
		}
		else
		{
			show_404();
		}
	}

	

	/**
	*
	* Output JSON survey metadata including citations, external resources, etc
	*
	**/
	function _survey_json($id=NULL)
	{
		if (!is_numeric($id))
		{
			return FALSE;
		}
		
		$this->load->model('Catalog_model');						
		
		//output JSON
		echo $this->Catalog_model->survey_to_json($id);
	}



	/**
	* show study related citations
	*
	*/
	function citations($id=NULL)
	{				
		if (!is_numeric($id))
		{
			show_404();
		}
		
		$this->load->model('Catalog_model');
		$this->load->model('Citation_model');
		$this->load->library('chicago_citation');
						
		//get survey
		$survey=$this->Catalog_model->select_single($id);
		
		if ($survey===FALSE)
		{
			show_404();
		}
		//$this->template->set_template('blank');	
		
		if ($this->input->get('ajax') || $this->input->get('print') )
		{
			$this->template->set_template('blank');	
		}
		
		//get survey folder path - NEEDED BY THE VIEW
		$this->survey_folder=$this->Catalog_model->get_survey_path_full($id);

		//get survey related citations
		$survey['citations']=$this->Citation_model->get_citations_by_survey($id);
		//get survey basic info
		$survey['survey']=$this->Catalog_model->get_survey($id);
		
		$content_body=$this->load->view('catalog_search/survey_summary_citations',$survey,TRUE);		
		$this->template->write('title', t('citations'),true);
		$this->template->write('content', $content_body,true);
	  	$this->template->render();
	}

	/**
	* Download survey related files e.g. questionnaire, reports, etc
	*
	*/
	/* todo: remove
	function download($id=NULL)
	{
		if (!is_numeric($id))
		{
			show_404();
		}
		
		$file=$this->uri->segment(4);
	
		if ($file=='')
		{
			show_404();
		}

		$file_name=trim(base64_decode($file));
		
		//required for getting ddi file path
		$this->load->model('Catalog_model');
				
		//get ddi file path from db
		$folder_path=$this->Catalog_model->get_survey_path_full($id);
	
		//complete file path	
		$file_path=$folder_path .'/'.$file_name;

		if (file_exists($file_path))
		{
			$this->load->helper('download');
			//download the file
			force_download2($file_path);
			return;
		}
		else
		{
			$file_name=prep_url($file_name);
			echo t('msg_website_redirect').' ';
			echo anchor($file_name,$file_name);
			echo js_redirect($file_name,0);
		}
	}
	*/
	
	function export($format='print')
	{
		$output= $this->_search();
		
		switch($format)
		{
			case 'print':
				if ($output['search_type']=='variable'){
					$content=$this->load->view('catalog_search/variable_list_print', $output,TRUE);
				}
				else{
					$content=$this->load->view('catalog_search/survey_list_print', $output,TRUE);
				}

				$this->template->set_template('blank');
				$this->template->write('title', t('studies'),true);
				$this->template->write('content', $content,true);
				$this->template->render();				
			break;
			
			case 'csv':
			
					if ($output['search_type']=='variable')
					{
						$rows=$output['rows'];
						$cols=explode(",",'uid,name,labl,varID,titl,nation');
					}
					else
					{
						$rows=$output['surveys']['rows'];
						$cols=explode(",",'id,surveyid,titl,nation,authenty,data_coll_start,data_coll_end,created,changed');
					}
					
					//var_dump($output['surveys']);exit;
			
					$filename='search-'.date("m-d-y-his").'.csv';
					header('Content-Encoding: UTF-8');
					header( 'Content-Type: text/csv' );
					header( 'Content-Disposition: attachment;filename='.$filename);
					$fp = fopen('php://output', 'w');
					
					echo "\xEF\xBB\xBF"; // UTF-8 BOM
			
					//add column names
					fputcsv($fp, $cols);
					
					foreach($rows as $row)
					{						
						$data=array();		
						foreach($cols as $col)
						{
							$data[$col]=$row[$col];
						}
						
						if( isset($data['changed'])){
							$data['changed']=date("M-d-y",$data['changed']);
							$data['created']=date("M-d-y",$data['created']);
						}
						
						fputcsv($fp, $data);
					}
					
					fclose($fp);

			break;
		}
	}
	
	
	function export_x($format='doc')
	{
		//$this->output->enable_profiler(TRUE);
		$page=$this->input->get('page');
		$page= ($page >0) ? $page : 1;
		$offset=($page-1)*$this->limit;
		$view=$this->input->get("view");

		//log
		$this->db_logger->write_log('search',$this->input->get("sk"),'study');
		$this->db_logger->write_log('search',$this->input->get("vk"),'question');

		//switch to variable view
		if ($this->input->get('vk')!='' && $view=='v')
		{
			//variable search
			//$surveys=$this->Advanced_search_model->vsearch(1000,$offset);
			$params=array(
				'study_keywords'=>$this->input->get_post('sk'),
				'variable_keywords'=>$this->input->get_post('vk'),
				'variable_fields'=>$this->input->get_post('vf'),
				'countries'=>$this->input->get_post('country'),
				'topics'=>$this->input->get_post('topic'),
				'from'=>$this->input->get_post('from'),
				'to'=>$this->input->get_post('to')
			);		
			$this->load->library('catalog_search',$params);
			$surveys=$this->catalog_search->vsearch($limit=5000);
			
			$surveys['current_page']=$page;
		}
		else
		{
			//survey view
			//$surveys=$this->Advanced_search_model->search($limit=200,$offset);
			$params=array(
				'study_keywords'=>$this->input->get_post('sk'),
				'variable_keywords'=>$this->input->get_post('vk'),
				'variable_fields'=>$this->input->get_post('vf'),
				'countries'=>$this->input->get_post('country'),
				'topics'=>$this->input->get_post('topic'),
				'from'=>$this->input->get_post('from'),
				'to'=>$this->input->get_post('to')
			);		
			$this->load->library('catalog_search',$params);
			$surveys=$this->catalog_search->search($limit=200);			
		}
		
		//echo '<pre>';
		//var_dump($surveys);exit;
		
		switch($format)
		{
			case 'doc':
				header("Content-type: application/vnd.ms-word");
				header('Content-Disposition: attachment; filename="search-export-'.date("U").'.doc"');
				$this->load->view('catalog_search/export_word',$surveys);
				return;
			break;
			
			default:
				//bulid a list of fields for export
				$export_array=array();
				foreach($surveys['rows'] as $row)
				{
					$row=(object)$row;
					$export_array[]=array
						(
							'surveyid'=>$row->refno,
							'title'=>$row->titl,
							'country'=>$row->nation,
							'primary-investigator'=>$row->authenty,
							'year'=>$row->proddate,
							'study-url'=>site_url().'/catalog/'.$row->id,
						);
				}
				
				
				header("Content-type: application/octet-stream");
				header('Content-Disposition: attachment; filename="search-export-'.date("U").'.csv"');
				
				echo $this->_array_to_scv($export_array);
				echo "\r\n\r\n\r\n";
				echo t('data_catalog').':, '. site_url().'/catalog/';
				echo "\r\n";
				echo 'Date:, '. date("M-d/Y",date("U"));
					
		}
	}
	
	
	function access_policy($id=NULL)
	{
		if (!is_numeric($id))
		{
			show_404();
		}
		
		$this->load->model('Catalog_model');
		$this->load->library('DDI_Browser','','DDI_Browser');
		$this->load->helper('url_filter');
		$this->load->library('cache');
		
		//get ddi file path from db
		$ddi_file=$this->Catalog_model->get_survey_ddi_path($id);
		
		//survey folder path
		$this->survey_folder=$this->Catalog_model->get_survey_path_full($id);
		
		if ($ddi_file===FALSE)
		{
			show_error(t('file_not_found'));
		}

		//log
		$this->db_logger->write_log('survey',$this->uri->segment(4),'accesspolicy',$id);

		//get survey info
		$survey=$this->Catalog_model->select_single($id);
		$this->survey=$survey;
		$this->ddi_file=$ddi_file;

		//language
		$language=array('lang'=>$this->config->item("language"));
		
		if(!$language)
		{
			//default language
			$language=array('lang'=>"english");
		}

		//get the xml translation file path
		$language_file=$this->DDI_Browser->get_language_path($language['lang']);
		
		if ($language_file)
		{
			//change to the language file (without .xml) in cache
			$language['lang']=unix_path(FCPATH.$language_file);
		}		
			    		
		$html=NULL;
		$section='accesspolicy';
		$html= $this->cache->get( md5($section.$ddi_file.$language['lang']));

		if ($html===FALSE)
		{	
			$html=$this->DDI_Browser->get_access_policy_html($ddi_file,$language);
			$html=html_entity_decode(url_filter($html));
			$this->cache->write($html, md5($section.$ddi_file.$language['lang']), 100);
		}
		
		$html='<h1>'.$survey['nation'].' - '.$survey['titl'].'</h1><br/><br/>'.$html;
		
		$this->template->add_css('themes/ddibrowser/ddi.css');
		$this->template->add_meta(sprintf('<link rel="canonical" href="%s" />',js_base_url().'catalog/access_policy/'.$id),NULL,'inline');
		$this->template->write('title', t('accesspolicy'),true);

		$this->template->write('content', $html,true);
	  	$this->template->render();
	}
	
	/**
	*
	* Redirect for external repositories
	*/
	function redirect($id=NULL)
	{
		if (!is_numeric($id))
		{
			show_404();
		}

		//get survey information
		$this->survey=$this->Catalog_model->select_single($id);
		
		if (!$this->survey)
		{
			show_404();
		}		

		//get survey repo + remote url
		$this->load->model('Repository_model');
		
		$this->survey_repo=$this->Repository_model->get_row($this->survey['repositoryid'],$this->survey['surveyid']);
		
		if (!$this->survey_repo)
		{
			show_404();
		}
		
		//log
		$this->db_logger->write_log('redirect',"{$this->survey['nation']} - {$this->survey['titl']} ",'study');

		$this->template->set_template('box');
		$this->template->write('title', 'Redirecting to external catalog',true);
		$content=$this->load->view('catalog_search/survey_redirect',NULL,true);
		$this->template->write('content', $content,true);
	  	$this->template->render();
	
	}

	/**
	* Generatting CSV formatted string from an array.
	* By Sergey Gurevich.
	*/
	function _array_to_scv($array, $header_row = true, $col_sep = ",", $row_sep = "\n", $qut = '"')
	{
		$output='';
		//Header row.
		if ($header_row)
		{
			foreach ($array[0] as $key => $val)
			{
				//Escaping quotes.
				$key = str_replace($qut, "$qut$qut", $key);
				$output .= "$col_sep$qut$key$qut";
			}
			$output = substr($output, 1)."\n";
		}
		
		
		//Data rows.
		foreach ($array as $key => $val)
		{
			$tmp = '';
			foreach ($val as $cell_key => $cell_val)
			{
				//Escaping quotes.
				$cell_val = str_replace($qut, "$qut$qut", utf8_decode($cell_val));
				$tmp .= "$col_sep$qut$cell_val$qut";
			}
			$output .= substr($tmp, 1).$row_sep;
		}
		
		return $output;
	}


	function _remap($method) 
	{
		$method=strtolower($method);
        
		if ($method=='search')
		{
			$this->_set_active_repo($this->input->get("repo"));
			$this->search();
		}
		else if (in_array(($method), array_map('strtolower', get_class_methods($this))) ) 
		{
            $uri = $this->uri->segment_array();
            unset($uri[1]);
            unset($uri[2]);
            call_user_func_array(array($this, $method), $uri);
        }
        else 
		{
			//valid repo?
			if ($this->_set_active_repo($method)!==false)
			{
				//about?
				if ($this->uri->segment(3)=='about')
				{
					$this->about_repository();return;
				}

				//load the default listing page
				$this->index();
			}
			else
			{
				show_404();
			}		
        }
    }
	
	private function _set_active_repo($repo)
	{
		$this->load->model("repository_model");

		$repo=trim(strtolower($repo));
		//get an array of all valid repository names from db
		$repositories=$this->Catalog_model->get_repository_array();
		$repositories[]='central';
		
		//repo names to lower case
		foreach($repositories as $key=>$value)
		{
			$repositories[$key]=strtolower($value);
		}
		
		//check if URI matches to a repository name 
		if (in_array($repo,$repositories))
		{				
			//repository options
			if ($repo=='central')
			{
				$this->active_repo=NULL;
			}
			else
			{
				//set active repo
				$this->active_repo=$this->repository_model->get_repository_by_repositoryid($repo);
			}
		}
	}
	
	
	function about_repository()
	{
		$repositoryid=$this->uri->segment(2);
		$this->load->model("repository_model");
		$additional_data=NULL;
		$repo=NULL;
		
		//unpublished repos are visible to limited admins or admins only
		$this->acl->user_has_unpublished_repo_access_or_die(NULL,$repositoryid);
		
		if ($repositoryid=='central')
		{
			$this->load->model("repository_model");
			$this->load->model("repository_sections_model");
			$collections=$this->repository_model->get_repositories($published=TRUE, $system=FALSE);
			$sections=array();
			
			foreach($collections as $key=>$collection)
			{
				$sections[$collection['section']]=$collection['section_title'];
			}
			
			$data['sections']=$sections;		
			$data['rows']=$collections;
			$data['show_unpublished']=FALSE;
			$additional_data=$this->load->view("repositories/index_public",$data,TRUE);
			$repo=array(
					'repositoryid'	=>'central',
					'title'			=>t('central_data_catalog')
			);
		}
		else
		{
			$repo=$this->repository_model->get_repository_by_repositoryid($repositoryid);
			
			if (!$repo)
			{
				show_404();
			}		
		}
		
		$page_data=array(
			'repo'=>$this->active_repo,
			'active_tab'=>'about',
			'repo_citations_count'=>$this->repository_model->get_citations_count_by_collection($this->active_repo['repositoryid'])
		);

		$page_data['content']=$this->load->view("catalog_search/about_collection",array('row'=>(object)$repo, 'additional'=>$additional_data),TRUE);
		$contents=$this->load->view("catalog_search/study_collection_tabs",$page_data,TRUE);
		
		//set page title
		$this->template->write('title', $repo['title'],true);
		$this->template->write('content', $contents,true);
	  	$this->template->render();
	}	
	
	
	/**
	*
	* A table showing when new studies were added
	**/
	function history()
	{
		$this->load->library("pagination");
		$this->load->model("Catalog_history_model");
		
		//records to show per page
		$per_page = $this->input->get("ps");
		
		if($per_page===FALSE || !is_numeric($per_page))
		{
			$per_page=100;
		}
				
		//current page
		$curr_page=$this->input->get('per_page');

		//filter to further limit search
		$filter=array();
		
		//records
		$data['rows']=$this->Catalog_history_model->search($per_page, $curr_page,$filter);

		//total records in the db
		$total = $this->Catalog_history_model->search_count;

		if ($curr_page>$total)
		{
			$curr_page=$total-$per_page;
			
			//search again
			$data['rows']=$this->Catalog_history_model->search($per_page, $curr_page,$filter);
		}
		
		//set pagination options
		$base_url = site_url('catalog/history');
		$config['base_url'] = $base_url;
		$config['total_rows'] = $total;
		$config['per_page'] = $per_page;
		$config['page_query_string'] = TRUE;
		$config['additional_querystring']=get_querystring( array('sort_by','sort_order','keywords', 'field','ps'));//pass any additional querystrings
		$config['next_link'] = t('page_next');
		$config['num_links'] = 5;
		$config['prev_link'] = t('page_prev');
		$config['first_link'] = t('page_first');
		$config['last_link'] = t('last');
		$config['full_tag_open'] = '<span class="page-nums">' ;
		$config['full_tag_close'] = '</span>';
		
		//intialize pagination
		$this->pagination->initialize($config); 
		
		//load the contents of the page into a variable
		$content=$this->load->view('catalog_search/history', $data,true);
	
		//pass data to the site's template
		$this->template->write('content', $content,true);
		$this->template->write('title', t('catalog_history'),true);
		
		//render final output
	  	$this->template->render();
	}
	
	/**
	*
	* Country selection dialog
	**/
	function country_selection($repo=NULL)
	{
		if($repo==NULL)
		{
			$repo=$this->active_repo['repositoryid'];
		}

		//check if a valid repo name
		if($this->Repository_model->repository_exists($repo)==0)
		{
			$repo='central';
		}
	
		$this->load->model("country_region_model");
		//regions+countries tree
		$data['regions']=$this->country_region_model->get_tree_region_countries($repo);
		//array of countries
		$data['countries']=$this->Search_helper_model->get_active_countries($repo);
		$data['repositoryid']=$repo;
		
		$this->load->view('catalog_search/country_selection',$data);
	}
	
	function get_country_selection_tab($repo=NULL,$section='alphabatical',$region_id=NULL)
	{
		if($repo==NULL)
		{
			$repo=$this->active_repo['repositoryid'];
		}

		//check if a valid repo name
		if($this->Repository_model->repository_exists($repo)==0)
		{
			$repo=NULL;
		}
	
		$this->load->model("country_region_model");
		
		//regions+countries tree
		$data['regions']=$this->country_region_model->get_tree_region_countries($repo);
		
		//array of countries
		$data['countries']=$this->Search_helper_model->get_active_countries($repo);
	
		switch($section)
		{
			case 'alphabatical':
				$this->load->view('catalog_search/country_selection_alphabatical',$data);
			break;
			
			case 'region':
				$data['region_id']=$region_id;
				$this->load->view('catalog_search/country_selection_regional',$data);
			break;
		}
	}
	
	//topic selection dialog
	function topic_selection($repo=NULL)
	{
		if($repo==NULL)
		{
			$repo=$this->active_repo['repositoryid'];
		}

		//check if a valid repo name
		if($this->Repository_model->repository_exists($repo)==0)
		{
			$repo=NULL;
		}
	
		$this->load->model("vocabulary_model");
		$data['topics']=$this->vocabulary_model->get_tree($vid=1,$active_only=TRUE,$repo);
		$this->load->view('catalog_search/topic_selection',$data);
	}
	
	function collection_selection()
	{
		$this->load->model('Repository_model');		
		$data['repositories']=$this->Repository_model->get_repositories_tree();
		$this->load->view('catalog_search/collection_selection',$data);
	}
	
	
	function help_da()
	{
		$this->load->view("catalog_search/help_da");
	}
	
	
	private function get_featured_study($surveys)
	{
		if (!is_array($surveys))
		{
			return FALSE;
		}
		
		$repos=NULL;
		
		//build an array of repositoryid
		foreach($surveys as $survey)
		{
			$repos[]=$survey['repositoryid'];
		}

		if (!$repos)
		{
			return FALSE;
		}
				
		//count values for each repository
		$repo_counts = array_count_values($repos);		
		
		//find the repo with most surveys
		$repositoryid = array_search(max($repo_counts), $repo_counts);
		
		//echo $repositoryid;
		
		//get the featured study for the selected repositoryid
		$featured_study=$this->Repository_model->get_featured_study($repositoryid);

		return $featured_study;
	}
}
/* End of file catalog.php */
/* Location: ./controllers/catalog.php */