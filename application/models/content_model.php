<?php

class Content_model extends CI_Model
{
	function Content_model()
	{
		parent::__construct();
		// autoload database, so the lines below is useless
	
		/*
		$config['hostname'] = "localhost";
		$config['username'] = "myusername";
		$config['password'] = "mypassword";
		$config['database'] = "mydatabase";
		$config['dbdriver'] = "mysql";
		$config['dbprefix'] = "";
		$config['pconnect'] = FALSE;
		$config['db_debug'] = TRUE;
		$config['cache_on'] = FALSE;
		$config['cachedir'] = "";
		$config['char_set'] = "utf8";
		$config['dbcollat'] = "utf8_general_ci";
		*/
		//$this->load->database();
	}
	
	
	function get_series($user_id)
	{
		$series_query = $this->db->query(
			"
			SELECT *
			FROM series
			WHERE uid={$user_id};
			"
		);
		
		return $series_query->result_array();
	}
	
	function get_other_series($user_id)
	{
		$series_query = $this->db->query(
			"
			SELECT *
			FROM series
			WHERE uid!={$user_id};
			"
		);
	
		return $series_query->result_array();
	}
	
	function get_single_series($seies_id)
	{
		$series_query = $this->db->query(
			"
			SELECT *
			FROM series
			WHERE id={$seies_id};
			"
		);
	
		return $series_query->row_array();
	}
	
	function get_all_series()
	{
		$series_query = $this->db->query(
			"
			SELECT *
			FROM series;
			"
		);
		
		return $series_query->result_array();
	}
	
	function get_author($series_id)
	{
		$series = $this->db->query(
			"
			SELECT uid
			FROM series
			WHERE id={$series_id};
			"
		)->row_array();
		
		$author_query = $this->db->query(
			"
			SELECT *
			FROM users
			WHERE id={$series["uid"]};
			"
		);
		
		return $author_query->row_array();
	}
	
	
	
	function get_images($series_id)
	{
		$images_query = $this->db->query(
			"
			SELECT *
			FROM images
			WHERE sid={$series_id};
			"
		);
		
		return $images_query->result_array();
	}
	
	function new_series($user_id, $user_email, $series_name)
	{
		// still needed to be edited
		
		$content_query= $this->db->query(
			"
			INSERT INTO series (name, uid, cover_id, cover_path, public)
			VALUES ('{$series_name}', {$user_id}, -1, '', 'private');
			"
		);
		
		if($content_query)
		{
			$hash_name = md5($series_name);
			$hash_email = md5($user_email);
			
			return mkdir("./images/{$hash_email}/{$hash_name}");
		}
		
		return false;
	}
	
	
	function rmdir_fully($dir)
	{
		if (is_dir($dir)) {
			$objects = scandir($dir);
			foreach ($objects as $object)
			{
				if ($object != "." && $object != "..")
				{
					if (filetype($dir."/".$object) == "dir")
					{
						rmdir($dir."/".$object);
					}
					else
					{
						unlink($dir."/".$object);
					}
				}
			}
			reset($objects);
			rmdir($dir);
		}
	}
	
	function delete_series($user_id, $user_email, $series_id)
	{	
		$series_name = $this->db->query(
			"
			SELECT name
			FROM series
			WHERE id={$series_id};
			"
		)->row_array()["name"]; // or use row() function???
		
		$delete_query = $this->db->query(
			"
			DELETE
			FROM series
			WHERE id={$series_id};
			"
		);
		
		$delete_query = $this->db->query(
			"
			DELETE
			FROM images
			WHERE sid={$series_id};
			"
		);
		
		$hash_name = md5($series_name);
		$hash_email = md5($user_email);
		$this->rmdir_fully("./images/{$hash_email}/{$hash_name}");
	}
	
	
	function add_images($user_id, $series_id, $image_files)
	{
		$author=$this->get_author($series_id);
	
		$series_query = $this->db->query(
			"
			SELECT name, cover_id
			FROM series
			WHERE id={$series_id};
			"
		);
		
		$series_name=$series_query->row_array()["name"];
		$hash_name=md5($series_name);
		$hash_email=md5($author["email"]);
		$cover_id=$series_query->row_array()["cover_id"];
		
		$value_array=[];
		$values_str="";
		
		$newFileDirectory = "./images/" .$hash_email. "/" .$hash_name;
		
		for($i=0; $i<count($image_files["name"]); $i++)
		{
			//Get the temp file path
			$tmpFilePath = $image_files["tmp_name"][$i];
			//mb_convert_encoding($tmpFilePath, "BIG5");
				
			//Make sure we have a filepath
			if ($tmpFilePath != "")
			{
				//Setup our new file path
				//$newFilePath = $newFileDirectory ."/". mb_convert_encoding($image_files["name"][$i], "BIG5");
				$ext=pathinfo($image_files["name"][$i])["extension"];
				$new_name=date("YmdHis", time()) . "_" . $i;
				$newFilePath = "images/" .$hash_email. "/" .$hash_name. "/{$new_name}.". $ext;
				
				//Upload the file into the temp dir
				if(move_uploaded_file($tmpFilePath, "./" . $newFilePath))
				{
					//$path_parts = pathinfo($newFilePath);
					
					//$new_name=date("YmdHis", time()) . "_" . $i;
					$original_name=$image_files["name"][$i];
					//$new_path = "images/" .$hash_email. "/" .$hash_name. "/{$new_name}.". $path_parts["extension"];
					
					//rename($newFilePath, $new_path);
					
					// (sid, file_name, original_name, ext, path, description)
					array_push($value_array, "({$series_id}, '{$new_name}', '{$original_name}', '{$ext}', '{$newFilePath}', '')");
				}
				else
				{
					return "error when move from temp directory to upload directory\n";
				}
			}
		}
		
		
		$values_str=join(",", $value_array);
		
		$content_query = $this->db->query(
			"
			INSERT INTO images (sid, file_name, original_name, ext, path, description)
			VALUES {$values_str};
			"
		);
		
		if($cover_id==-1)
		{
			$image_info = $this->db->query(
				"
				SELECT path, id
				FROM images
				WHERE sid={$series_id};
				"
			)->row_array();
			
			$content_query = $this->db->query(
				"
				UPDATE series
				SET cover_path='{$image_info["path"]}', cover_id={$image_info["id"]}
				WHERE id={$series_id};
				"
			);
		}
		
		return "";
	}
	
	
	function change_description($image_id, $description)
	{
		$this->db->query(
			"
			UPDATE images
			SET description = '{$description}'
			WHERE id={$image_id}
			"
		);
	}
	
	
	function delete_images($series_id, $image_ids)
	{
		/*
		$ids_str="";
		for($i=0; $i<count($image_ids); $i++)
		{
			$ids_str=$ids_str."{$image_ids[$i]}, ";
		}
		$ids_str=substr($ids_str, 0, strlen($ids_str)-1);
		*/
		
		$iids=join("," , $image_ids);
		
		$paths = $this->db->query(
			"
			SELECT path
			FROM images
			WHERE id in ({$iids});
			"
		)->result_array();
		
		$series = $this->db->query(
			"
			SELECT name, cover_id, cover_path
			FROM series
			WHERE id={$series_id};
			"
		)->row_array();
		
		foreach ($paths as $result)
		{
			unlink("./" .$result["path"]);
		}
		
		
		$delete_query = $this->db->query(
			"
			DELETE
			FROM images
			WHERE id in ({$iids});
			"
		);
		
		
		// cover image....
		
		foreach ($image_ids as $image_id)
		{
			if($image_id==$series["cover_id"])
			{
				$image_query = $this->db->query(
					"
					SELECT path, id
					FROM images
					WHERE sid={$series_id};
					"
				);
				
				if($image_query->num_rows() > 0)
				{
					$image_info=$image_query->row_array();
					
					$content_query = $this->db->query(
						"
						UPDATE series
						SET cover_path='{$image_info["path"]}', cover_id={$image_info["id"]}
						WHERE id={$series_id};
						"
					);
				}
				else
				{
					$content_query = $this->db->query(
						"
						UPDATE series
						SET cover_path='', cover_id=-1
						WHERE id={$series_id};
						"
					);
				}
				
				break;
			}
		}
	}
	
	
	function change_series_name($series_id, $new_name, $user_email)
	{
		$new_hash=md5($new_name);
		
		$old_name=$this->db->query(
			"
			SELECT name
			FROM series
			WHERE id={$series_id}
			"
		)->row_array();
		$old_hash=md5($old_name["name"]);
		
		$this->db->query(
			"
			UPDATE series
			SET name = '{$new_name}'
			WHERE id={$series_id}
			"
		);
		
		$owner_hash=md5($user_email);
		
		rename("images/{$woner_hash}/{$old_hash}/", "images/{$woner_hash}/{$new_hash}/");
		
		$images=get_images($series_id);
		foreach($images as $image)
		{
			$this->db->query(
				"
				UPDATE images
				SET path = 'images/{$owner_hash}/{$new_hash}/{$image["file_name"]}.{$image["ext"]}'
				WHERE id={$image["id"]}
				"
			);
		}
	}
	
	function change_series_cover($series_id, $image_path)
	{
	}
	
	function change_auth($series_id, $auth)
	{
		$this->db->query(
			"
			UPDATE series
			SET public = '{$auth}'
			WHERE id={$series_id}
			"
		);
	}
	
	
}
	
?>