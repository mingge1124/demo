<?php
/********
* 数据结构：
* 省份 => [市 => [区 => [ 街道 ]]]

*
*
********/

class AreaSpider{
	
	
	public $province = [];
	public $city = [];
	public $base_url = 'http://www.stats.gov.cn/tjsj/tjbz/tjyqhdmhcxhfdm/2019/';
	
	
	public function parseProvince()
	{
		
		if (is_file('./province.txt')) {
			$html_content = file_get_contents('./province.txt');
		} else {
			$html_content = $this->curl_get($this->base_url);
			file_put_contents('./province.txt', $html_content);	
		}
		
		
		//正则提取
		preg_match_all('/<trclass=\'provincetr\'>(.*?)<\/tr>/', $html_content, $provinces);


		$province_str = implode('',$provinces[1]);
		
		preg_match_all('/<td>(.*?)<\/td>/', $province_str, $province_arr);
		
		$province_arr = $province_arr[1];
		
		
		foreach($province_arr as $value) {
			preg_match('/<ahref=\'(.*?)\'>(.*?)<br\/><\/a>/', $value,$info_arr);
			
			if (isset($info_arr[2])) {
				$this->province[] = [
					'url' => $info_arr[1],
					'name' => $info_arr[2]
				];
			}
			
		}
	}
	
	public function parseCity()
	{

		foreach($this->province as $key => $value) {
			$tmp_file_name = './city/' . $value['url'] . '.txt';
			if (is_file($tmp_file_name)) {
				$html_content = file_get_contents($tmp_file_name);
			} else {
				$url = $this->base_url . $value['url'];
				$html_content = $this->curl_get($url);
				file_put_contents($tmp_file_name, $html_content);
			}
			
			
			preg_match_all('/<trclass=\'citytr\'>(.*?)<\/tr>/', $html_content,$cities);
			
			
			foreach($cities[1] as $city) {
				preg_match_all('/<td><ahref=\'(.*?)\'>(.*?)<\/a><\/td><td><ahref=\'(.*?)\'>(.*?)<\/a><\/td>/', $city, $city_info);
				$this->province[$key]['city_list'][] = [
					'url' => $city_info[1][0],
					'num' => $city_info[2][0],
					'name' => $city_info[4][0]
				];
			}	
		}	
	}
	
	public function parseRegion()
	{
		foreach($this->province as $pkey => $pvalue) {
			foreach($pvalue['city_list'] as $ckey => $cvalue) {
				$tmp_file_name = './region/' . str_replace('/', '_', $cvalue['url']) . '.txt';
				if (is_file($tmp_file_name)) {
					$html_content = file_get_contents($tmp_file_name);
				} else {
					$url = $this->base_url . $cvalue['url'];
					$html_content = $this->curl_get($url);
					file_put_contents($tmp_file_name, $html_content);
				}
			
				preg_match_all('/<trclass=\'countytr\'>(.*?)<\/tr>/', $html_content,$regions);
				
				//"<td><ahref='01/110119.html'>110119000000</a></td><td><ahref='01/110119.html'>延庆区</a></td>
				foreach($regions[1] as $region) {
					preg_match_all('/<td><ahref=\'(.*?)\'>(.*?)<\/a><\/td><td><ahref=\'(.*?)\'>(.*?)<\/a><\/td>/', $region, $region_info);
					if (isset($region_info[1][0]) && $region_info[1][0]) {
						$this->province[$pkey]['city_list'][$ckey]['region_list'][] = [
							'url' => (int)$pvalue['url'] . '/' . $region_info[1][0],
							'num' => $region_info[2][0],
							'name' => $region_info[4][0]
						];
					}
				}					
			}
		}
	}
	
	
	public function parseStreet()
	{
		foreach($this->province as $pkey => $pvalue) {
			foreach($pvalue['city_list'] as $ckey => $cvalue) {
				foreach ($cvalue['region_list'] as $rkey => $rvalue) {
					$tmp_file_name = './street/' . str_replace('/', '_', $rvalue['url']) . '.txt';
					if (is_file($tmp_file_name)) {
						$html_content = file_get_contents($tmp_file_name);
					} else {
						$url = $this->base_url . $rvalue['url'];
						$html_content = $this->curl_get($url);
						file_put_contents($tmp_file_name, $html_content);
					}
				
					preg_match_all('/<trclass=\'towntr\'>(.*?)<\/tr>/', $html_content,$streets);
					if (!isset($streets[1]) || !$streets[1]) {
						var_dump($streets);die;
					}
					
					//"<tr class="towntr"><td><a href="02/540102002.html">540102002000</a></td><td><a href="02/540102002.html">八廓街道</a></td></tr>
					foreach($streets[1] as $street) {
						preg_match_all('/<td><ahref=\'(.*?)\'>(.*?)<\/a><\/td><td><ahref=\'(.*?)\'>(.*?)<\/a><\/td>/', $street, $street_info);
						if (isset($street_info[1][0]) && $street_info[1][0]) {
							$this->province[$pkey]['city_list'][$ckey]['region_list'][$rkey]['street_list'][] = [
								'num' => $street_info[2][0],
								'name' => $street_info[4][0]
							];
					
						}
					}					
				}
			}
		}
		file_put_contents('./area.js', json_encode($this->province));
	}
	
	
	public function curl_get($url) {
		$cu = curl_init();
		curl_setopt($cu, CURLOPT_URL, $url);
		curl_setopt($cu, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($cu, CURLOPT_CONNECTTIMEOUT, 10);
		curl_setopt($cu, CURLOPT_ENCODING, 'gzip');
		$content = curl_exec($cu);
		curl_close($cu);
		return $this->format_html($content);
	}
	
	public function format_html($content)
	{
		$content = mb_convert_encoding($content, 'utf-8', 'gbk');
		return preg_replace("/\s/", "", $content);
	}

}

$spider = new AreaSpider();
$spider->parseProvince();
$spider->parseCity();
$spider->parseRegion();
$spider->parseStreet();