<?php


function Grab_Lenta() {
	include('simple_html_dom.php');
	$site = 'http://www.tmsk.lenta.com';
	$page = '/special-nye-predlozheniya/';
	$page_period ='/prodovol-stvennye-tovary/';

	$url = "${site}${page}";		
	$html_code = file_get_contents($url);
// получаем продукты
	$startsAt = strpos($html_code, "myTovarList.items=") + strlen("myTovarList.items=");
	$endsAt = strpos($html_code, "myTovarList.order", $startsAt);
	$contents = substr($html_code, $startsAt, $endsAt - $startsAt - 14);
	$contents = preg_replace_callback('/\\\\u([0-9a-fA-F]{4})/', function ($match) {
    return mb_convert_encoding(pack('H*', $match[1]), 'UTF-8', 'UTF-16BE');
    }, $contents);
	$products = json_decode($contents);

// вытаскиваем период действия скидки - с другой страницы
	$host = $site.$page_period;
	$ua = 'Mozilla/5.0 (Windows NT 6.3; WOW64; rv:51.0) Gecko/20100101 Firefox/51.0';
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $host);
	curl_setopt($ch, CURLOPT_HEADER, 0); // Если не нужны заголовки в ответе
	curl_setopt($ch, CURLOPT_USERAGENT, $ua);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER,true);
	$html_code = curl_exec ($ch);
	curl_close ($ch);

	$dom = new simple_html_dom();
	$dom->load($html_code);
	$text = "";
	$content = $dom->find("main[class='page']", 0);
	foreach ($content->find("p") as $p ) {
		$text .= $p->plaintext;
	}
	preg_match_all('/(\d{2}).(\d{2}).(\d{4})/', $text, $matches);
	$period = implode(' - ', $matches[0]);

	foreach ($products as $product) {
		$item['title'] = $product->name;
	    $item['text'] = $product->short_text1.''.$product->short_text2;
		$item['curprice'] = preg_replace("/[^\d.]/", "", $product->new_price);
	    $item['oldprice'] = $product->old_price;
		if ($item['oldprice'] == 0) {
			$item['oldprice'] = $item['curprice'];
			$item['period'] = 'ПОСТОЯННО';
		} else $item['period'] = $period;
	    $item['image'] = "${site}".$product->picture2;
	    
	    $result[] = $item;
		unset($item);
	}

	return $result;

}

function Grab_Yarche() {
	include('simple_html_dom.php');
	$site = 'https://xn--80akiai2b0bl4e.xn--p1ai';
	// лимит выгрузки товаров!
	$limit = 10;
	$host = $site.'/category/0/?offset=1&cnt='.$limit;
	$ua = 'Mozilla/5.0 (Windows NT 6.3; WOW64; rv:51.0) Gecko/20100101 Firefox/51.0';
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $host);
	curl_setopt($ch, CURLOPT_HEADER, 0); // Если не нужны заголовки в ответе
	curl_setopt($ch, CURLOPT_USERAGENT, $ua);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER,true);
	$html_code = curl_exec ($ch);
	curl_close ($ch);

	$json_decoded = json_decode($html_code);
	$dom = new simple_html_dom();
	$dom->load($json_decoded->html);
//	echo $dom->save();

	$item = [];
	$dom2 = new simple_html_dom();
	foreach($dom->find("li[class='product']") as $product) {
		$item['title'] = $product->find("h3[class='product-title']", 0)->innertext;
		/***********************************   тащим описание продукта из модалки ***********************/
		$descr_id = $product->find("div[class='product-clickable']", 0)->getAttribute('data-id');
		$host = $site.'/product/'.$descr_id.'/';
		$ua = 'Mozilla/5.0 (Windows NT 6.3; WOW64; rv:51.0) Gecko/20100101 Firefox/51.0';
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $host);
		curl_setopt($ch, CURLOPT_HEADER, 0);
		curl_setopt($ch, CURLOPT_USERAGENT, $ua);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER,true);
		$html_code = curl_exec ($ch);
		curl_close ($ch);
		$dom2->load($html_code);
		$item['text'] = "";
		$description = $dom2->find("article[class='product-description']", 0);
		foreach ($description->find("p") as $p ) {
			$item['text'] .= $p->innertext . " ";
		}
		/**********************************************************************************************/
		$period = $product->find("div[class='action-days_remains']", 0)->innertext;
		$cur_price = $product->find("strong[class='action-new_price']", 0)->innertext;
		$item['curprice'] = preg_replace('/\.$|[^\d.]/mi', '', $cur_price);
		$old_price = $product->find("mark[class='product-price']", 0)->innertext;
		if ($old_price == 0) $old_price = $cur_price;
		$item['oldprice'] = preg_replace('/\.$|[^\d.]/mi', '', $old_price);

		$image_node = $product->find("img[class='product-image']", 0)->getAttribute('data-src');
		if ($image_node) {
			$item['image'] = $site.$image_node;
		}
		// парсим период действия акции
		$_monthsList = array(" января" => ".01.", "февраля" => ".02.",
			" марта" => ".03.", " апреля" => ".04.", " мая" => ".05.", " июня" => ".06.",
			" июля" => ".07.", " августа" => ".08.", " сентября" => ".09.",
			" октября" => ".10.", " ноября" => ".11.", " декабря" => ".12.");
		$period = trim($period);
		$year = date("Y");
		foreach ($_monthsList as $word => $num) {
			if ($period != '') {
				if (strpos($period, $word)) $period = str_replace($word, $num . $year, $period);
			} else $period = 'ПОСТОЯННО';
		}
		$item['period'] = $period;

		$result[] = $item;
		unset($item);
	}
//	print_r($result);
	return $result;
}


	function prepare_str($string) {
		$str = strip_tags($string);
		$str = str_replace('"', '', $str);
		$str = htmlspecialchars($str);
		return $str;
	}

?>
