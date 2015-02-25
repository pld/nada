<?php

/**
* Relationship types
*
* 	Type 1: require double entries in the db and both end have different names
*
*	Examples: 
*		parent -> child
*		child -> parent
*
*
*	Type 2: require double entries and there is no need to define both ends of the relationships
*		
*	Examples:
*		isWaveOf -> isWaveOf
*		or 
*		isRelatedTo	->	isRelatedTo
*
*
*	Type 3: Relationship not set
*
*	uses the relationship-id=0 and only one entry is required
*
**/


class Related_study_model extends CI_Model {

    public function __construct()
    {
        parent::__construct();
		//$this->output->enable_profiler(TRUE);
    }
	
	/**
	*
	* add/update relationship
	*
	*/
	public function update_relationship($p_sid,$c_sid_arr,$relation_id)
	{	
		foreach($c_sid_arr as $sid_2)
		{
			//get relationship pair by relation_id
			$rel_pairs=$this->get_relationship_pairs($relation_id);

			//delete existing relationship pair
			$this->delete_relationship($p_sid,$sid_2,$relation_id);

			//rules
			//1:	0	no relationship type set - dont insert double records
			//2:	if rel_pairs row count =1 then use the same relationshipid for both sides

			//unique key for the pair
			$pair_key=date("U");

			foreach($rel_pairs as $key=>$relation)
			{
				if($key==$relation_id)
				{
					$options=array(
						'sid_1'				=>	$p_sid,
						'sid_2'				=>	$sid_2,
						'relationship_id'	=>	$relation['id'],
						'pair_id'			=>	$pair_key
					);
					
				}
				else
				{
					$options=array(
						'sid_2'				=>	$p_sid,
						'sid_1'				=>	$sid_2,
						'relationship_id'	=>	$relation['id'],
						'pair_id'			=>	$pair_key
					);
					
				}
				
									
				//add new relationship
				$result=$this->db->insert('survey_relationships', $options); 

				if ($result===false)
				{
					throw new MY_Exception($this->db->_error_message());
				}			
			}
		}
		return TRUE;
	}
	
	/**
	*
	* returns the relationship type pair by id
	**/
	public function get_relationship_pairs($relation_id)
	{
		$this->db->select("id,rel_name");
		$this->db->where( sprintf('rel_group_id in (select rel_group_id from survey_relationship_types where id=%d)',(int)$relation_id),NULL,FALSE);
		$rows=$this->db->get("survey_relationship_types")->result_array();
		
		$output=array();
		foreach($rows as $row)
		{
			$output[$row['id']]=$row;
		}
				
		//for relationships like isWaveOf <---> isWaveOf
		if(count($rows)==1 && $rows[0]["id"]!=0)
		{
			//duplicate the row
			$output[]=$rows[0];
		}
		
		return $output;
	}
	
	
	
	public function delete_relationship($sid_1,$sid_2,$relation_id)
	{
		//get pairs
		$rel_pairs=$this->get_relationship_pairs($relation_id);
		
		//delete both relationship entries
		foreach($rel_pairs as $key=>$relation)
		{
			if($key==$relation_id)
			{
				$p_sid=$sid_1;
				$c_sid=$sid_2;
				
				$this->db->where('sid_1',$p_sid);
				$this->db->where('sid_2',$c_sid);
				$this->db->where('relationship_id',$relation['id']);
				$this->db->delete('survey_relationships');				
			}
			else
			{
				$p_sid=$sid_2;
				$c_sid=$sid_1;
				
				$this->db->where('sid_1',$p_sid);
				$this->db->where('sid_2',$c_sid);
				$this->db->where('relationship_id',$relation['id']);
				$this->db->delete('survey_relationships');				
			}
		}
		
	}
		
	
	public function get_relationships($sid)
	{
		$this->db->select('surveys.id as sid,surveys.titl, surveys.nation,surveys.data_coll_start, survey_relationships.*');
		$this->db->where('sid_1',$sid);
		$this->db->join('surveys','surveys.id=survey_relationships.sid_2','INNER');
		return $this->db->get('survey_relationships')->result_array();
	}
	
	
	/**
	*
	* Return an array of relationship types as pairs
	**/	
	public function get_relationship_types_array()
	{
		$sql='select * from (
			select 
				srt1.id,
				srt1.rel_group_id,
				srt1.rel_name,
				srt1.rel_dir,
				srt2.rel_name as rel_name2, 
				srt2.rel_dir as rel_dir2
			from survey_relationship_types srt1
				inner join survey_relationship_types srt2 on srt1.rel_group_id=srt2.rel_group_id
				where srt1.rel_dir=0 and srt2.rel_dir=1
			UNION ALL
			select 
				srt1.id,
				srt1.rel_group_id,
				srt1.rel_name,
				srt1.rel_dir,
				srt2.rel_name as rel_name2, 
				srt2.rel_dir as rel_dir2
			from survey_relationship_types srt1
				inner join survey_relationship_types srt2 on srt1.rel_group_id=srt2.rel_group_id
				where srt1.rel_dir=1 and srt2.rel_dir=0
			UNION ALL
			select 
				id, 
				rel_group_id, 
				rel_name, 
				rel_dir, 
				rel_name as rel_name2, 
				rel_dir as rel_dir2
			from survey_relationship_types
				where rel_dir=2
			)
			relationship_types order by id;';
		
		$types=$this->db->query($sql)->result_array();
		
		$output=array();
		
		$no_duplicates=array();
		
		foreach($types as $type)
		{
			$output[$type['id']]=$type['rel_name'].' ---> ('.$type['rel_name2'].')';
		}
		
		return $output;
	}
}