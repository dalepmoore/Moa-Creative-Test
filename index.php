<!DOCTYPE html>
<html lang="EN">
	<head>
		<title>Moa Programming Test</title>
		<meta name="description" content="" />
		<meta name="keywords" content="" />
		<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
		<script src="https://ajax.googleapis.com/ajax/libs/jquery/2.1.3/jquery.min.js"></script> 
	</head>
	<body>
	<?php
	require_once("json_encode.php");
	?>
	</body>
	<style type="text/css">
		ul {list-style:none;cursor:pointer;font-size:18px;display:block;}
		li {cursor:pointer;padding:3px 0 3px 0;}
		.visible {display:block !important;}
		.hidden {display:none !important;}
		li.plus {color:#000;}
		li.minus {color:#333;}
		li.plus:before {content:"+   ";}
		li.minus:before {content:"-   ";}
		li.nochildren:before {content:"";}
		li.parent_list{padding-bottom:10px;margin-bottom:10px;border-bottom:1px solid black;}
		</style>
	<script type="text/javascript">
	$(document).ready(function(){
		//get the json file saved by json_encode.php, convert it to a nested html list structure and append to the document body
		$.getJSON("<?php echo $dest?>",function(data){
			var list=json2html(data);
			$(list).appendTo("body");
			replace_bullet();
		});
		//expand and collapse nodes if clicked
		$(document.body).on('click','li',(function(e){
			if($(this).hasClass('nochildren')){
				return false;
			}
			e.stopPropagation();
			//hide subnodes when a parent node is collapsed
			$(this).children('ul').toggleClass('hidden visible');
			$(this).toggleClass('plus minus');
			if($(this).hasClass('plus')){
				$(this).find('ul').removeClass('visible').addClass('hidden');
				$(this).find('li').removeClass('minus').addClass('plus');
			}
		}));
	});
	//if a <li> has no children remove the + or -
	function replace_bullet(){
		$('li').not(':has(ul)').addClass('nochildren');
		$('li').not(':has(ul)').removeClass('minus plus');
		hide_all_but_top_level();
	}
	//collapse all but top level <li>
	function hide_all_but_top_level(){
		$("ul").each(function() {
			if($(this).parents('li').length > 0) {
				$(this).toggleClass('hidden visible');
			}
			else{
				$(this).children('li').addClass('parent_list');
			}
		});
	}
	//recursively parse the json to build nested html lists
	function json2html(json){
		var i;
		list="";
		list += "<ul>";
		for(i in json){
			if(json[i]!=""){
				list += "<li class=\"plus visible\">"+i+": ";
				if( typeof json[i] === "object"){
					list += json2html(json[i]);
				}
				else{
					list += json[i];
				}
				list += "</li>";
			}
		}
		list += "</ul>";
		return list;
	}
	</script>
</html>