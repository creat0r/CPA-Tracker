<?php
	set_time_limit(0);
	
	$settings_file=dirname (__FILE__).'/cache/settings.php';
	$str=file_get_contents($settings_file);
	$str=str_replace('<? exit(); ?>', '', $str);
	$arr_settings=unserialize($str);

	$_DB_LOGIN=$arr_settings['login'];
	$_DB_PASSWORD=$arr_settings['password'];
	$_DB_NAME=$arr_settings['dbname'];
	$_DB_HOST=$arr_settings['dbserver'];

	include dirname(__FILE__)."/connect.php";

	$arr_files=array();
	$process_at_once=60;
	$iCnt=0;
	if ($handle = opendir(dirname(__FILE__).'/cache/clicks/')) 
	{
	    while (false !== ($entry = readdir($handle))) {
	        if ($entry != "." && $entry != ".." && $entry != ".empty") 
	        {
		        if (
				        // Check if file starts with dot,  
			        	(strpos($entry, '.')===0) && 
			        	// is not processing now
			        	(strpos($entry, '+')===false) && 
			        	// and was not processed before
			        	(strpos($entry, '*')===false)
		        	)
		        {
		        	// Also check that there were at least 2 minutes from creation date
		        	if ($entry!='.clicks_'.date('Y-m-d-H-i', strtotime('-1 minutes')) &&
		        	$entry!='.clicks_'.date('Y-m-d-H-i')
		        	)
		        	{
			        	$arr_files[]=$entry;
		        		if (($iCnt++) > $process_at_once)
		        		{
		        			break;
		        		}			        	
		        	}
		        }
	        }
	    }
	    closedir($handle);
	}
	
	if (count ($arr_files)==0){exit();}

        if (extension_loaded('xmlreader')) {
            // Init WURFL library for mobile device detection
            $wurflDir = dirname(__FILE__) . '/lib/wurfl/WURFL';
            $resourcesDir = dirname(__FILE__) . '/lib/wurfl/resources';	
            require_once $wurflDir.'/Application.php';
            $persistenceDir = dirname(__FILE__).'/cache/wurfl-persistence';
            $cacheDir = dirname(__FILE__).'/cache/wurfl-cache';	
            $wurflConfig = new WURFL_Configuration_InMemoryConfig();
            $wurflConfig->wurflFile($resourcesDir.'/wurfl.zip');
            $wurflConfig->matchMode('accuracy');
            $wurflConfig->allowReload(true);
            $wurflConfig->persistence('file', array('dir' => $persistenceDir));
            $wurflConfig->cache('file', array('dir' => $cacheDir, 'expiration' => 36000));
            $wurflManagerFactory = new WURFL_WURFLManagerFactory($wurflConfig);
            $wurflManager = $wurflManagerFactory->create();
        }

	foreach ($arr_files as $cur_file)
	{
		$file_name=dirname(__FILE__)."/cache/clicks/{$cur_file}+";
		rename (dirname(__FILE__)."/cache/clicks/$cur_file", $file_name);
		$handle = fopen($file_name, "r");
	    while (($buffer = fgets($handle, 4096)) !== false) 
	    {
		    $arr_click=array();
	        $arr_click=explode ("\t", rtrim($buffer, "\n"));
	        save_click_info ($arr_click);
	    }
	    fclose($handle);
		rename ($file_name, dirname(__FILE__)."/cache/clicks/{$cur_file}*");
	}

	exit();


	function get_hour_by_date($str)
	{
		$a=end(explode (' ', $str));
		return current(explode (':', $a));	
	}

	function _str($str)
	{
		return mysql_real_escape_string ($str);
	}	
	
	function get_geodata($ip)
	{
		require_once (dirname (__FILE__)."/lib/maxmind/geoip.inc.php");
		require_once (dirname (__FILE__)."/lib/maxmind/geoipcity.inc.php");
		require_once (dirname (__FILE__)."/lib/maxmind/geoipregionvars.php");
		$gi = geoip_open(dirname (__FILE__)."/lib/maxmind/MaxmindCity.dat", GEOIP_STANDARD);
		$record = geoip_record_by_addr($gi, $ip);
		geoip_close($gi);

		$giisp = geoip_open(dirname(__FILE__)."/lib/maxmind-isp/GeoIPISP.dat",GEOIP_STANDARD);
		$isp = geoip_org_by_addr($giisp, $ip);

		return array ('country'=>$record->country_code, 'state'=>$GEOIP_REGION_NAME[$record->country_code][$record->region], 'city'=>$record->city, 'region'=>$record->region, 'isp'=>$isp);
	}
	
	function save_click_info ($arr_click_info)
	{
		// User-agent parser
		require_once ("lib/ua-parser/uaparser.php");
		$parser = new UAParser;

		// WURFL mobile database
		global $wurflManager;
		
		$click_date=$arr_click_info[0];
		$click_day=current(explode(' ', $click_date));
		$click_hour=get_hour_by_date ($click_date);
		
		$click_ip=$arr_click_info[1];

		// Get geo from IP
		$geo_data=get_geodata($click_ip);
		$click_country=$geo_data['country'];
		$click_state=$geo_data['state'];
		$click_city=$geo_data['city'];
		$click_region=$geo_data['region'];
		$click_isp=$geo_data['isp'];

		// Get info from user agent
		$click_user_agent=$arr_click_info[2];		
		
		// Set empty initial values
		$is_mobile_device=false; $is_tablet=false; $is_phone=false; $brand_name=''; $model_name=''; $model_extra_info=''; 
		$device_os=''; $device_os_version=''; $device_browser=''; $device_browser_version='';

                if (extension_loaded('xmlreader')) {
                    $requestingDevice = $wurflManager->getDeviceForUserAgent($click_user_agent);

                    $is_wireless = ($requestingDevice->getCapability('is_wireless_device') == 'true');
                    $is_tablet = ($requestingDevice->getCapability('is_tablet') == 'true');
                    $is_mobile_device = ($is_wireless || $is_tablet);

                    // Use WURFL database info for mobile devices only	
                    if ($is_mobile_device)
                    {	
                            $is_phone = ($requestingDevice->getCapability('can_assign_phone_number') == 'true');

                            $brand_name=$requestingDevice->getCapability('brand_name');
                            $model_name=$requestingDevice->getCapability('model_name');
                            $model_extra_info=$requestingDevice->getCapability('model_extra_info');

                            $device_os = $requestingDevice->getCapability('device_os');
                            $device_os_version = $requestingDevice->getCapability('device_os_version');				

                            $device_browser = $requestingDevice->getCapability('mobile_browser');
                            $device_browser_version = $requestingDevice->getCapability('mobile_browser_version');
                    }
                    else
                    {
                            // Use UAParser to get click info
                            $result = $parser->parse($click_user_agent);

                            $device_browser=$result->ua->family;
                            $device_browser_version=$result->ua->toVersionString;

                            $device_os=$result->os->family;
                            $device_os_version=$result->os->toVersionString;
                    }
                }
		
		$click_referer=$arr_click_info[3];
		$click_link_name=$arr_click_info[4];
		$click_link_source=$arr_click_info[5];
		
		// Allow to use - as campaign/ads delimiter
		$link_ads_name=$arr_click_info[6];
		if (strpos($link_ads_name, '-')!==false)
		{
			$click_link_campaign=current(explode('-', $link_ads_name));
			$click_link_ads=substr($link_ads_name, strpos($link_ads_name, '-')+1);			
		}
		else
		{
			$click_link_campaign=$link_ads_name;
			$click_link_ads='';
		}

		$click_subid=$arr_click_info[7];
		$click_subaccount=$arr_click_info[8];
		$click_rule_id=$arr_click_info[9];
		$click_out_id=$arr_click_info[10];
		$click_param1=$arr_click_info[11];
		$click_param2=$arr_click_info[12];
		$click_param3=$arr_click_info[13];
		$click_param4=$arr_click_info[14];
		$click_param5=$arr_click_info[15];
		
		// Parse get string
		parse_str ($arr_click_info[16], $click_get_params);
		$i=1;
		$sql_click_params=array();

		$is_connected=false; $connected_subid='';
		foreach ($click_get_params as $param_name=>$param_value)
		{
			if ($param_name=='_subid')
			{
				$pattern = '/\d{14}x\d{5}/';
				preg_match_all($pattern, $param_value, $subids);
				foreach($subids[0] as $t_key=>$t_subid)
				{
					if ($t_subid!='')
					{
						$is_connected=true;					
						$connected_subid=$t_subid;
					}
					break;
				}
				continue;
			}

			$sql_click_params[]="click_param_name{$i}='"._str($param_name)."', click_param_value{$i}='"._str($param_value)."'";
			$i++;

			// Maximum 15 get parameters allowed
			if ($i>15){break;}
		}
		$sql_click_params=implode (', ', $sql_click_params);
		if (strlen($sql_click_params)>0){
			$sql_click_params=", {$sql_click_params}";
		}

		// Click from landing page
		if ($is_connected)
		{
			// Get parent click id
			$sql="select id from tbl_clicks where subid='"._str($connected_subid)."' limit 1";
			$result=mysql_query($sql);
			$row=mysql_fetch_assoc($result);
			if ($row['id']>0)
			{
				$parent_id=$row['id'];
				$sql="update tbl_clicks set is_parent=1 where id='"._str($parent_id)."'";
				mysql_query($sql);
			}
		}

		$sql="insert into tbl_clicks SET
				date_add='"._str($click_date)."', 
				date_add_day='"._str($click_day)."', 
				date_add_hour='"._str($click_hour)."', 
				user_ip='"._str($click_ip)."', 
				user_agent='"._str($click_user_agent)."', 
				user_os='"._str($device_os)."', 
				user_os_version='"._str($device_os_version)."', 				
				user_platform='"._str($brand_name)."', 
				user_platform_info='"._str($model_name)."', 		
				user_platform_info_extra='"._str($model_extra_info)."',			
				user_browser='"._str($device_browser)."', 
				user_browser_version='"._str($device_browser_version)."',					
				is_mobile_device='"._str($is_mobile_device)."', 
				is_phone='"._str($is_phone)."', 		
				is_tablet='"._str($is_tablet)."', 					
				country='"._str($click_country)."', 
				state='"._str($click_state)."', 
				city='"._str($click_city)."', 
				region='"._str($click_region)."', 
				isp='"._str($click_isp)."', 
				rule_id='"._str($click_rule_id)."', 
				out_id='"._str($click_out_id)."', 
				subid='"._str($click_subid)."', 
				is_connected='"._str($is_connected)."', 
				parent_id='"._str($parent_id)."', 
				subaccount='"._str($click_subaccount)."', 
				source_name='"._str($click_link_source)."', 
				campaign_name='"._str($click_link_campaign)."', 
				ads_name='"._str($click_link_ads)."', 
				referer='"._str($click_referer)."', 
				search_string='', 
				campaign_param1='"._str($click_param1)."', 
				campaign_param2='"._str($click_param2)."', 
				campaign_param3='"._str($click_param3)."', 
				campaign_param4='"._str($click_param4)."', 
				campaign_param5='"._str($click_param5)."'
				{$sql_click_params}";
		mysql_query($sql);
			
	}
?>