<?php
$db_host= "rd878o.mysql.rds.aliyuncs.com:3306"; //MYSQL服务器名
$db_user="gz"; //MYSQL用户名
$db_pass="B2"; //MYSQL用户对应密码
$db_name="uk_database"; //要操作的数据库
//使用mysql_connect()函数对服务器进行连接，如果出错返回相应信息
$link=mysql_connect($db_host,$db_user,$db_pass)or die("不能连接到服务器".mysql_error());
mysql_select_db($db_name,$link); //选择相应的数据库，
mysql_query('SET NAMES utf8');
date_default_timezone_set("America/Los_Angeles");
?>
<?php
header("content-type: text/xml");
 echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";

$my_url = 'http://u.us/twilio-php/app/testapp/callingapp/iddcalldb.php';
if (isset($_REQUEST['From'])){
			$iFrom = preg_replace("#[^+0-9]#", '',$_REQUEST['From']);
			$iFrom = substr($iFrom,-10);
			}
			
echo '<Response>';
if (array_key_exists('passcode_check', $_GET)) {
  if ($_POST["Digits"] == '899') { //密码正确则加到idd库，就下次不用输入密码了
  		
	  $sql_insert =  "INSERT INTO `uskk_database`.`idduser` (`id`, `username`, `password`, `phone`, `dayleft`, `minleft`, `blacklist`, `logtime`) VALUES (default, NULL, NULL, '$iFrom', DATE_ADD(NOW() , INTERVAL 32 DAY), '1999', '', 'default')";
	 $result=mysql_query($sql_insert,$link); 
    echo '<Gather timeout="12" finishOnKey="#" action="' . $my_url . '?dial">';
	echo '<Say voice="alice" language="zh-CN">拨打中国，请不要挂机继续输入0086加中国号码，然后等待15秒钟自动接通。八零零和九几几等特殊号码不可以拨打，请百度他们的座机。</Say>';
    echo '</Gather>';
  } else {
 

     echo '<Say voice="alice" language="zh-CN">密码错误，请咨询24小时客服获取密码。</Say>';
     echo '<Hangup/>';
  }
} else if (array_key_exists('dial', $_GET)) {
  echo '<Say voice="alice" language="zh-CN">正在为您接通</Say>';
  echo '<Dial timeout="30" timelimit ="1900" action="' . $my_url . '?call_complete">';
  echo $_POST["Digits"];
  echo '</Dial>';
} else if (array_key_exists('call_complete', $_GET)) {
  if ($_POST["DialCallStatus"] == "busy") {
    echo '<Say voice="alice" language="zh-CN">对方正忙，请稍后再试</Say>';
  
  } else if ($_POST["DialCallStatus"] == "no-answer") {
    echo '<Say voice="alice" language="zh-CN">无人接听，请稍后再试</Say>';
  } else if ($_POST["DialCallStatus"] == "failed") {
    echo '<Say voice="alice" language="zh-CN">号码错误，请检查您输入的号码，拨打中国，输入0086加中国号码，城市区号前的0请去掉。八零零和九几几等特殊号码不可以拨打，请百度他们的座机</Say>';
  } else if ($_POST["DialCallStatus"] == "completed") {
	  $iDialCallDuration = $_POST["DialCallDuration"];
	  $iDialCallDuration = $iDialCallDuration/60;
    echo '<Say voice="alice" language="zh-CN">通话结束，您的通话时间为'.$iDialCallDuration.'分钟</Say>';
	
	//写数据库减少时间
	$sql = "UPDATE `uskk_database`.`idduser` SET `minleft`= minleft - $iDialCallDuration  WHERE  `phone`='$iFrom' ";
	//$sql = "UPDATE `uskk_database`.`idduser` SET `minleft`= '155',  WHERE (`phone`='$iFrom')";
	$result2=mysql_query($sql,$link);  
	 $num_rows  = mysql_num_rows($result2);
  }
  echo '<Pause length="1"/>';
  echo '<Hangup/>';
} else {
 // 判断权限$iFrom
 
 if (checkidd($iFrom) > 0){
	 		echo '<Gather timeout="10" finishOnKey="#" action="' . $my_url . '?dial">';
			echo '<Say voice="alice" language="zh-CN">拨打中国，请不要挂机继续输入0086加中国号码，然后等待15秒钟自动接通。八零零和九几几等特殊号码不可以拨打，请百度他们的座机。</Say>';
    		echo '</Gather>';
 }else{
	 
	 			echo '<Gather timeout="20" finishOnKey="#" action="' . $my_url. '?passcode_check" method="POST">';
				echo '<Say voice="alice" language="zh-CN">';
				echo '欢迎使用贝壳国际长途服务,请输入密码，如无密码，请咨询24小时淘宝客服。';
				echo '</Say>';
				echo '</Gather>';
				echo '<Say voice="alice" language="zh-CN">';
				echo '输入超时，再见';
				echo '</Say>';
}
 
			
}
echo '</Response>';



function checkidd($iFrom){
	
global $link;

	if($iFrom != ''){ //有来电显示
	$sql =  'select * from idduser where replace(replace(replace(replace(replace(replace(replace(phone," ",""),"－",""),"（",""),"-",""),"(",""),")",""),"）","") like "%'.$iFrom.'%" ORDER BY id DESC limit 1';
	 $result=mysql_query($sql,$link);  
	 $num_rows  = mysql_num_rows($result);
	 if($num_rows > 0){ //idd表有数据
		$row=mysql_fetch_array($result);
		$idayleft = $row[dayleft];
		$iminleft = $row[minleft];
		$iblacklist = $row[blacklist];
		$today = date("Y-m-d");
		
		if ($iblacklist > 1){ //黑名单马上断
		return -2;
		};
		
		if ($idayleft > $today and $iminleft >0 and $iblacklist < 1){ //idd表的日期和时间有效则接通
			return 1;
		}else if ($iblacklist < 1){ //没有在黑名单则查找主库
			 $sql = 'SELECT * FROM activation WHERE replace(replace(replace(replace(replace(replace(replace(MSISDN," ",""),"－",""),"（",""),"-",""),"(",""),")",""),"）","") like "%'.$iFrom.'%" and ACTDate >= DATE_ADD(NOW() , INTERVAL -32 DAY) ORDER BY ACTDate DESC limit 1';
	 		$result=mysql_query($sql,$link);  
	 		$num_rows  = mysql_num_rows($result);
		 	if($num_rows > 0){ //主库有则更新有效期
			$row=mysql_fetch_array($result);
				$sql_update =  "UPDATE `uskk_database`.`idduser` SET `dayleft`= DATE_ADD(NOW() , INTERVAL 32 DAY), `minleft`='1999' WHERE (`phone`='$iFrom')";
			 
				$result=mysql_query($sql_update,$link); 
				 
				return 1;
			}
		

		}
		}		 //idd表无记录则查找主表是否有
		 	 
			
			$sql = 'SELECT * FROM activation WHERE replace(replace(replace(replace(replace(replace(replace(MSISDN," ",""),"－",""),"（",""),"-",""),"(",""),")",""),"）","") like "%'.$iFrom.'%" and ACTDate >= DATE_ADD(NOW() , INTERVAL -32 DAY) ORDER BY ACTDate DESC limit 1';
	 		$result=mysql_query($sql,$link);  
	 		$num_rows  = mysql_num_rows($result);
			
		 
		 	if($num_rows > 0){   //有则往idd表加
	 
				$row=mysql_fetch_array($result);
				$sql_insert =  "INSERT INTO `uskk_database`.`idduser` (`id`, `username`, `password`, `phone`, `dayleft`, `minleft`, `blacklist`, `logtime`) VALUES (default, NULL, NULL, '$iFrom', DATE_ADD(NOW() , INTERVAL 32 DAY), '1999', '', 'default')";

 
				$result=mysql_query($sql_insert,$link); 
			return 1;
    		echo '</Gather>';
			}else{ //无则提示要密码
				return 0;
			
		}
	}
}


?>
