<?php
//the html file to parse, can be an array or a string
$urls = array("local.html","http://testing.moacreative.com/job_interview/php/index.html");
//the place to save the produced json.
$dest = "films.json";
//run the function
html_to_json($urls,$dest,TRUE);

//parent function, setting $silent to FALSE will produce verbose output
function html_to_json($urls,$dest,$silent=TRUE){
	//if the file to parse is a string convert it to an array
	if(is_string($urls)){
		$urlarray[]=$urls;
		$urls=$urlarray;
	}
	//check if input is valid
	if(!is_array($urls) || count($urls)<1 || (count($urls)==1 && empty($urls[0]))){
		die("Unrecognised input for source file(s)<br>\n");
	}
	//if it is begin processing
	else if(is_array($urls)){
		$success=0;
		$fno=0;
		//how long to wait before retrying failed CURL request
		$retry_frequency=5;
		//how many times to retry a failed CURL request
		$number_of_attempts=10;
		foreach($urls as $url){
			$fno++;
			echo $silent ? "" : "<br>\nRetrieving file ".$fno." of ".count($urls)."...";
			for($a=1;$a<=$number_of_attempts;$a++,curl_close($ch)){
				//if its a local file get the contents without using curl and end execution of the current loop
				if(stripos($url,"http://")===FALSE){
					if(stripos($url,"/")===FALSE){
						$url = dirname(__FILE__)."/".$url;
					}
					$output=file_get_contents($url);
					echo $silent ? "" : "<br>\nSuccess (".$url.")<br>\n";
					$success++;
					break;
				}
				$ch = curl_init();
				curl_setopt($ch, CURLOPT_URL, $url);
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
				curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE); 
				curl_setopt($ch, CURLOPT_CONNECTTIMEOUT,90);
				curl_setopt($ch, CURLOPT_TIMEOUT,300);
				$output = curl_exec($ch);
				$info = curl_getinfo($ch);
				//check for curl error
				if(curl_errno($ch)!=0){
					echo $silent ? "" : "<br>\nAttempt ".$a." CURL Error: ".curl_errno($ch)." - ".curl_error($ch).", ";
					if($a<=($number_of_attempts-1)){
						echo $silent ? "" : "retrying in $retry_frequency seconds";
						sleep($retry_frequency);
						continue;
					}
					else {
						echo $silent ? "" : "final attempt failed<br>\n";
						curl_close($ch);
						continue 2;
					}
				}
				//check http response
				if($info['http_code']!='200'){
					echo $silent ? "" : "<br>\nAttempt ".$a." HTTP Response: ".$info['http_code']." - Failed, ";
					if($a<=($number_of_attempts-1)){
						echo $silent ? "" : "retrying in $retry_frequency seconds";
						sleep($retry_frequency);
						continue;
					}
					else {
						echo $silent ? "" : "final attempt failed<br>\n";
						curl_close($ch);
						continue 2;
					}
				}
				else{
					echo $silent ? "" : "<br>\nSuccess (".$url.")<br>\n";
					$success++;
					break;
				}
			}
			//repair improperly closed anchor tags in the input
			$html=str_replace("<a>","</a>",$output);
			//create a new DOMDocument object and load the html into it
			$dom=new DOMDocument();
			$dom->loadHTML($html);
			$dom->normalizeDocument;
			//pass the object to the parsing function
			$array[$url]["html"]=parse_html($dom->documentElement);
		}
	}
	echo $silent ? "" : "<br>\n".$success." of ".count($urls)." Files Retrieved<br>\n";
	//encode the multidimension array to json format
	$json=json_encode($array,JSON_PRETTY_PRINT);
	//save the json to a file
	if(file_put_contents($dest,$json)){
		echo $silent? "" : "<br>\nJSON written to ".$dest."<br>\n";
	}
	else {
		echo "<br>\nError writing file<br>\n";
	}
	return $json;
}
//extract properties from css
function parse_css($css){
	foreach($css as $subelement){
		$css=$subelement->wholeText;
	}
	preg_match_all("/(.+?{.+?:.+?;.+?})/s",$css,$matches);
	foreach($matches[0] as $css){
		$key=trim(substr($css,0,strpos($css,"{")));
		preg_match("/{(.+?):(.+?);/s",$css,$properties);
		$proparray[trim($properties[1])]=trim($properties[2]);
		$array[$key]=$proparray;
	}
	return $array;
}
//iterate through the DOM document object element by element and construct a multidimensional array
function parse_html($element){
	static $counter=0;
	$tagname=$element->tagName;
	//get the attributes of the current element, uncomment the commented sections to construct a child_node for attributes
	if($element->hasAttributes()){
		foreach($element->attributes as $attribute){
			$name=$attribute->name;
			$value=$attribute->value;
			$array/*["attributes"]*/[$name]=$value;
		}
		/*if(!is_array($array["attributes"])){
			unset($array["attributes"]);
		}*/
	}
	//if its a style tag use the parse_css function
	if($tagname=="style"){
		$array["child_nodes"]=parse_css($element->childNodes);
	}
	else{
		//if the element has child nodes recurse the parse_html function
		if($element->hasChildNodes()){
			foreach($element->childNodes as $subelement){
				if($subelement->nodeType==XML_TEXT_NODE){
					if(trim($subelement->wholeText)!=""){
						$array["content"]=trim($subelement->wholeText);
					}
				}
				else{
					if($subelement->nodeType==XML_ELEMENT_NODE){
						$id=$subelement->getAttribute('id');
					}
					if($id!=""){
						$namesuffix="#".$id;
					}
					//if the element doesnt have a unique id check if it has siblings of the same type and assign them a unique indentifier to prevent overwriting of the associative key in the generated array.
					else if(has_sibling_of_same_type($subelement,$subelement->tagName)){
						$namesuffix="(".$counter.")";
						$counter++;
					}
					else {
						$namesuffix="";
					}
					$array["child_nodes"][$subelement->tagName.$namesuffix]=parse_html($subelement);
				}
			}
		}
	}
	return $array;
}
//check if an element without a unique id has siblings of the same name.
function has_sibling_of_same_type($element,$name){
	$siblings=$element->nextSibling;
	while(!is_null($siblings)){
		if($siblings->tagName==$name){
			return TRUE;
		}
		$siblings=$siblings->nextSibling;
	}
	$siblings=$element->previousSibling;
	while(!is_null($siblings)){
		if($siblings->tagName==$name){
			return TRUE;
		}
		$siblings=$siblings->previousSibling;
	}
	return FALSE;
}
?>
