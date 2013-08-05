<?php
/**
 * KBoard 업그레이더
 * @link www.cosmosfarm.com
 * @copyright Copyright 2013 Cosmosfarm. All rights reserved.
 * @license http://www.gnu.org/licenses/gpl.html
 */
final class KBUpgrader {
	
	static private $instance;
	static private $latest_version;
	static private $sever_host = 'cosmosfarm.com';
	
	static $CONNECT_VERSION = 'http://www.cosmosfarm.com/wpstore/kboard/version';
	static $CONNECT_KBOARD = 'http://www.cosmosfarm.com/wpstore/kboard/getkboard';
	static $CONNECT_COMMENTS = 'http://www.cosmosfarm.com/wpstore/kboard/getcomments';
	
	static $TYPE_PLUGINS = '/plugins';
	static $TYPE_THEMES = '/themes';
	static $TYPE_KBOARD_SKIN = '/plugins/kboard/skin';
	static $TYPE_COMMENTS_SKIN = '/plugins/kboard-comments/skin';
	
	private function __construct(){
		
	}

	/**
	 * 인스턴스를 반환한다.
	 * @return KBoardSkin
	 */
	static public function getInstance(){
		if(!self::$instance) self::$instance = new KBUpgrader();
		return self::$instance;
	}
	
	static function connect($url){
		$host = self::$sever_host;
		$fp = @fsockopen($host, 80, $errno, $errstr, 30);
		if($fp){
			fputs($fp, "GET ".$url." HTTP/1.0\r\n"."Host: $host\r\n"."Referer: ".$_SERVER['HTTP_HOST']."\r\n"."\r\n");
			while(!feof($fp)){
				$output .= fgets($fp, 1024);
			}
			fclose($fp);
			$data = @explode("\r\n\r\n", $output);
			$data = @end($data);
			return json_decode($data);
		}
		else{
			$data->error = '코스모스팜 서버에 접속할 수 없습니다.';
			return $data;
		}
	}
	
	/**
	 * 서버에서 최신버전을 가져온다.
	 * @return string
	 */
	static function getLatestVersion(){
		if($_SESSION['kboard_latest_version']){
			self::$latest_version = $_SESSION['kboard_latest_version'];
		}
		else if(!self::$latest_version){
			$data = self::connect(self::$CONNECT_VERSION);
			if($data->error){
				echo 'null';
			}
			else{
				self::$latest_version = $data;
			}
		}
		
		//$_SESSION['kboard_latest_version'] = self::$latest_version;
		return self::$latest_version;
	}
	
	/**
	 * 패키지 파일을 다운받는다.
	 * @param string $package
	 * @param string $version
	 * @return string
	 */
	public function download($package, $version=''){
		//로컬에 있는 파일인지 확인한다.
		if(!preg_match('!^(http|https|ftp)://!i', $package) && file_exists($package)){
			return $package;
		}
		
		$download_file = download_url($package.'?host='.$_SERVER['HTTP_HOST'].'&version='.$version);
		
		if(is_wp_error($download_file)){
			die('<script>alert("업데이트 실패 : 서버 연결 실패, 잠시 후 다시 시도해 주세요.");history.go(-1);</script>');
		}
		
		return $download_file;
	}
	
	/**
	 * 패키지 파일의 압축을 풀고 설치한다.
	 * @param string $package
	 * @param string $content_type
	 * @param string $delete_package
	 * @return string
	 */
	public function install($package, $content_type, $delete_package = true){
		$file_handler = new KBFileHandler();
		$upgrade_folder = WP_CONTENT_DIR . '/upgrade/';
		$upgrade_files = $file_handler->getDirlist($upgrade_folder);
		$working_dir = $upgrade_folder . basename($package, '.zip');
		
		foreach($upgrade_files as $file){
			$file_handler->delete($upgrade_folder . $file);
		}
		
		include ABSPATH . 'wp-admin/includes/file.php';
		$result = unzip_file($package, $working_dir);
		
		if($delete_package) unlink($package);
		
		if(!$result){
			$file_handler->delete($working_dir);
			die('<script>alert("업데이트 실패 : 압축 해제 실패, 디렉토리 권한을 확인하세요.");history.go(-1);</script>');
		}
		else{
			$file_handler->copy($working_dir, WP_CONTENT_DIR . $content_type);
		}
		
		return $working_dir;
	}
}
?>