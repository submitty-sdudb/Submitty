<?php
namespace app\views\forum;

use app\authentication\DatabaseAuthentication;
use app\views\AbstractView;
use app\models\Course;
use app\libraries\FileUtils;


class ForumThreadView extends AbstractView {


	public function forumAccess(){
        return $this->core->getConfig()->isForumEnabled();
    }

    public function searchResult($threads){

    	$this->core->getOutput()->addBreadcrumb("Discussion Forum", $this->core->buildUrl(array('component' => 'forum', 'page' => 'view_thread')));

    	$return = <<<HTML

    	<style>
	    	.hoverable:hover {
			    -webkit-filter: brightness(85%);
			    -webkit-transition: all .5s ease;
			    -moz-transition: all .5s ease;
			    -o-transition: all .5s ease;
			    -ms-transition: all .5s ease;
			    transition: all .5s ease;
			}
    	</style>

    	<div style="margin-top:5px;background-color:transparent; margin: !important auto;padding:0;padding-left:20px;padding-right:20px;box-shadow: none;" class="content">

		<div style="background-color: #E9EFEF; box-shadow:0 2px 15px -5px #888888;margin-top:10px;border-radius:3px; height:40px; margin-bottom:10px;" id="forum_bar">


		<a class="btn btn-primary" style="position:relative;top:3px;left:5px;" title="Back to threads" href="{$this->core->buildUrl(array('component' => 'forum', 'page' => 'view_thread'))}"><i class="fa fa-arrow-left"></i> Back to Threads</a>

			<a class="btn btn-primary" style="position:relative;top:3px;left:5px;" title="Create thread" onclick="resetScrollPosition();" href="{$this->core->buildUrl(array('component' => 'forum', 'page' => 'create_thread'))}"><i class="fa fa-plus-circle"></i> Create Thread</a>

			<form style="float:right;position:relative;top:3px;right:5px;display:inline-block;" method="post" action="{$this->core->buildUrl(array('component' => 'forum', 'page' => 'search_threads'))}">
			<input type="text" size="35" placeholder="search" name="search_content" id="search_content" required/>
			<button type="submit" name="search" title="Submit search" class="btn btn-primary">
  				<i class="fa fa-search"></i> Search
			</button>
			</form>
			
		</div>

		<div id="search_wrapper">

    	<table style="" class="table table-striped table-bordered persist-area table-hover">

    	<thead class="persist-thead">
            <tr>                
                <td width="45%">Post Content</td>
                <td width="25%">Author</td>
                <td width="10%">Timestamp</td>
            </tr>	

        </thead>

        <tbody>


HTML;
		$threadArray = array();
		$fromIdtoTitle = array();
		foreach($threads as $thread){
			if(!array_key_exists($thread["thread_id"], $threadArray)) {
				$threadArray[$thread["thread_id"]] = array();
				$fromIdtoTitle[$thread["thread_id"]] = $thread["thread_title"];
			}
			$threadArray[$thread["thread_id"]][] = $thread;
		}
		$count = 1;
		foreach($threadArray as $thread_id => $data){
			$thread_title = htmlentities($fromIdtoTitle[$thread_id], ENT_QUOTES | ENT_HTML5, 'UTF-8');
			$return .= <<<HTML
			<tr class="info persist-header hoverable" title="Go to thread" style="cursor: pointer;" onclick="window.location = '{$this->core->buildUrl(array('component' => 'forum', 'page' => 'view_thread', 'thread_id' => $thread_id))}';">            
				<td colspan="10" style="text-align: center"><h4>{$thread_title}</h4></td>
			</tr>
HTML;
			foreach($data as $post) {
				$author = htmlentities($post['author'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
				$full_name = $this->core->getQueries()->getDisplayUserNameFromUserId($post["p_author"]);
				$first_name = htmlentities(trim($full_name["first_name"]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
				$last_name = htmlentities(trim($full_name["last_name"]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
				$visible_username = $first_name . " " . substr($last_name, 0 , 1) . ".";

				if($post["anonymous"]){
					$visible_username = 'Anonymous';
				} 

				//convert legacy htmlentities being saved in db
                $post_content = html_entity_decode($post["post_content"], ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $pre_post = preg_replace('#(<a href=[\'"])(.*?)([\'"].*>)(.*?)(</a>)#', '[url=$2]$4[/url]', $post_content);

                if(!empty($pre_post)){
                    $post_content = $pre_post;
				}
			
				$post_content = htmlentities($post_content, ENT_QUOTES | ENT_HTML5, 'UTF-8');
				$posted_on = date_format(date_create($post['timestamp_post']), "n/j g:i A");
				$return .= <<<HTML

				<tr title="Go to post" style="cursor: pointer;" onclick="window.location = '{$this->core->buildUrl(array('component' => 'forum', 'page' => 'view_thread', 'thread_id' => $thread_id))}#{$post['p_id']}';" id="search-row-{$author}" class="hoverable">
	                <td align="left"><pre style="font-family: inherit;"><p class="post_content" style="white-space: pre-wrap; ">{$post_content}</p></pre></td>
	                <td>{$visible_username}</td>
	                <td>{$posted_on}</td>      

		        </tr>
	            

HTML;
				$count++;
			}
		}
		
            

        $return .= <<<HTML

        </tbody>

        </table>
HTML;

		if(count($threads) == 0) {
		$return .= <<<HTML
			<h4 style="padding-bottom:20px;text-align:center;margin-top:20px;">No threads match your search criteria.</h4>
HTML;
		}

    	$return .= <<<HTML
    	</div> </div> 
HTML;
    	return $return;
    }
	
	/** Shows Forums thread splash page, including all posts
		for a specific thread, in addition to all of the threads
		that have been created to be displayed in the left panel.
	*/
	public function showForumThreads($user, $posts, $threads, $display_option, $max_thread) {
		if(!$this->forumAccess()){
			$this->core->redirect($this->core->buildUrl(array('component' => 'navigation')));
			return;
		}

		$thread_count = count($threads);
		$currentThread = -1;
		$currentCategoryId = array();

		$this->core->getOutput()->addBreadcrumb("Discussion Forum", $this->core->buildUrl(array('component' => 'forum', 'page' => 'view_thread')));
		
		//Body Style is necessary to make sure that the forum is still readable...
		$return = <<<HTML

		<link rel="stylesheet" href="{$this->core->getConfig()->getBaseUrl()}css/iframe/codemirror.css" />
		<link rel="stylesheet" href="{$this->core->getConfig()->getBaseUrl()}css/iframe/eclipse.css" />
		<script type="text/javascript" language="javascript" src="{$this->core->getConfig()->getBaseUrl()}js/iframe/jquery-2.0.3.min.map.js"></script>
		<script type="text/javascript" language="javascript" src="{$this->core->getConfig()->getBaseUrl()}js/iframe/codemirror.js"></script>
		<script type="text/javascript" language="javascript" src="{$this->core->getConfig()->getBaseUrl()}js/iframe/clike.js"></script>
		<script type="text/javascript" language="javascript" src="{$this->core->getConfig()->getBaseUrl()}js/iframe/python.js"></script>
		<script type="text/javascript" language="javascript" src="{$this->core->getConfig()->getBaseUrl()}js/iframe/shell.js"></script>
		<script type="text/javascript" language="javascript" src="https://cdnjs.cloudflare.com/ajax/libs/jquery.AreYouSure/1.9.0/jquery.are-you-sure.min.js"></script>
		<style>body {min-width: 925px;} pre { font-family: inherit; }</style>



		<script>
		function openFile(directory, file, path ){
			window.open("{$this->core->getConfig()->getSiteUrl()}&component=misc&page=display_file&dir=" + directory + "&file=" + file + "&path=" + path,"_blank","toolbar=no,scrollbars=yes,resizable=yes, width=700, height=600");
		}

			$( document ).ready(function() {
			    enableTabsInTextArea('.post_content_reply');
				saveScrollLocationOnRefresh('thread_list');
				saveScrollLocationOnRefresh('posts_list');
				$("form").areYouSure();
				addCollapsable();
				$('#{$display_option}').attr('checked', 'checked'); //Saves the radiobutton state when refreshing the page
			});

		</script>

HTML;
	if($this->core->getUser()->getGroup() <= 2){
		$return .= <<<HTML
			<script>
				function changeName(element, user, visible_username, anon){
					var new_element = element.getElementsByTagName("strong")[0];
					anon = (anon == 'true');
					icon = element.getElementsByClassName("fa fa-eye")[0];
					if(icon == undefined){
						icon = element.getElementsByClassName("fa fa-eye-slash")[0];
						if(anon) {
							new_element.style.color = "black";
							new_element.style.fontStyle = "normal";
						}
						new_element.innerHTML = visible_username;
						icon.className = "fa fa-eye";
						icon.title = "Show full user information";
					} else {
						if(anon) {
							new_element.style.color = "grey";
							new_element.style.fontStyle = "italic";
						}
						new_element.innerHTML = user;
						icon.className = "fa fa-eye-slash";
						icon.title = "Hide full user information";
					} 									
				}
			</script>
HTML;
	}
	if($thread_count > 0) {
		$currentThread = isset($_GET["thread_id"]) && is_numeric($_GET["thread_id"]) && (int)$_GET["thread_id"] < $max_thread && (int)$_GET["thread_id"] > 0 ? (int)$_GET["thread_id"] : $posts[0]["thread_id"];
		$currentCategoriesIds = $this->core->getQueries()->getCategoriesIdForThread($currentThread);
		$currentCategoriesIds_string  = implode("|", $currentCategoriesIds);
	}
	$return .= <<<HTML
		<div style="margin-top:5px;background-color:transparent; margin: !important auto;padding:0px;box-shadow: none;" class="content">
		<div style="background-color: #E9EFEF; box-shadow:0 2px 15px -5px #888888;border-radius:3px;margin-left:20px;margin-top:10px; height:40px; margin-bottom:10px;margin-right:20px;" id="forum_bar">
		<a class="btn btn-primary" style="position:relative;top:3px;left:5px;" title="Create thread" onclick="resetScrollPosition();" href="{$this->core->buildUrl(array('component' => 'forum', 'page' => 'create_thread'))}"><i class="fa fa-plus-circle"></i> Create Thread</a>
HTML;
	if($this->core->getUser()->getGroup() <= 2){
		
		$return .= <<<HTML
			<a class="btn btn-primary" style="margin-left:10px;position:relative;top:3px;right:5px;display:inline-block;" title="Show Stats" onclick="resetScrollPosition();" href="{$this->core->buildUrl(array('component' => 'forum', 'page' => 'show_stats'))}">Stats</a>
HTML;
	}
	$categories = $this->core->getQueries()->getCategories();
	$onChange = '';
	if($thread_count > 0) {
		$onChange = <<<HTML
		modifyThreadList({$currentThread}, '{$currentCategoriesIds_string}');
HTML;
	}
	$return .= <<<HTML
		<a class="btn btn-primary" style="margin-left:10px;position:relative;top:3px;right:5px;display:inline-block;" title="Filter Threads based on Categories" onclick="$('#category_wrapper').css('display', 'block');"><i class="fa fa-filter"></i> Filter</a>

		<div id="category_wrapper" class="popup-form" style="width: 50%;">
			<label for="thread_category"><h3>Categories</h3></label><br/>
			<i>For no filter, unselect all categories</i><br/>
			<center>
			<select id="thread_category" name="thread_category" class="form-control" multiple size="10" style="height: auto;">
HTML;
			for($i = 0; $i < count($categories); $i++){
				$return .= <<<HTML
					<option value="{$categories[$i]['category_id']}" style="color: {$categories[$i]['color']}">{$categories[$i]['category_desc']}</option>
HTML;
			}

	$return .= <<<HTML
				</select>
				</center>
				<br/>
				<div  style="float: right; width: auto; margin-top: 10px;">
					<a class="btn btn-default" title="Clear Filter" onclick="$('#thread_category option').prop('selected', false);{$onChange};$('#category_wrapper').css('display', 'none');"><i class="fa fa-eraser"></i> Clear Filter</a>
					<a class="btn btn-default" title="Close Popup" onclick="$('#category_wrapper').css('display', 'none');"><i class="fa fa-times"> Close</i></a>
				</div>

				<script type="text/javascript">
					$( document ).ready(function() {
						$('#thread_category option').mousedown(function(e) {
							e.preventDefault();
							var current_selection = $(this).prop('selected');
							$(this).prop('selected', !current_selection);
							{$onChange}
							return true;
						});
					});
				</script>
			</div>
			<button class="btn btn-primary" style="float:right;position:relative;top:3px;right:5px;display:inline-block;" title="Display search bar" onclick="this.style.display='none'; document.getElementById('search_block').style.display = 'inline-block'; document.getElementById('search_content').focus();"><i class="fa fa-search"></i> Search</button>
HTML;
			$return .= <<<HTML
			<input type="radio" name="selectOption" id="tree" onclick="changeDisplayOptions('tree', {$currentThread})" value="tree">  
			<label for="radio">Hierarchical</label>  

			<input type="radio" name="selectOption" id="time" onclick="changeDisplayOptions('time', {$currentThread})" value="time">  
			<label for="radio2">Chronological</label>
HTML;
	if($this->core->getUser()->getGroup() <= 2){
			$return .= <<<HTML
			<input type="radio" name="selectOption" id="alpha" onclick="changeDisplayOptions('alpha', {$currentThread})" value="alpha">  
			<label for="radio3">Alphabetical</label>
HTML;
	}
	$return .= <<<HTML
			<form id="search_block" style="float:right;position:relative;top:3px;right:5px;display:none;" method="post" action="{$this->core->buildUrl(array('component' => 'forum', 'page' => 'search_threads'))}">
			<input type="text" size="35" placeholder="search" name="search_content" id="search_content"/>

			<button type="submit" name="search" title="Submit search" class="btn btn-primary">
  				<i class="fa fa-search"></i> Search
			</button>
			</form>
HTML;
		$return .= <<<HTML
		</div>

HTML;
		if($thread_count == 0){
		$return .= <<<HTML
					<div style="margin-left:20px;margin-top:10px;margin-right:20px;padding:25px; text-align:center;" class="content">
						<h4>A thread hasn't been created yet. Be the first to do so!</h4>
					</div>
				</div>
HTML;
		} else {

			if($this->core->getUser()->getGroup() <= 2){
				$current_thread_first_post = $this->core->getQueries()->getFirstPostForThread($currentThread);
				$current_thead_date = date_create($current_thread_first_post["timestamp"]);
				$merge_thread_list = array();
				for($i = 0; $i < count($threads); $i++){
					$first_post = $this->core->getQueries()->getFirstPostForThread($threads[$i]["id"]);
					$date = date_create($first_post['timestamp']);
					if($current_thead_date>$date) {
						array_push($merge_thread_list, $threads[$i]);
					}
				}

				$return .= $this->core->getOutput()->renderTwigTemplate("forum/MergeThreadsForm.twig", [
                    "merge_thread_list" => $merge_thread_list,
                    "currentThread" => $currentThread
                ]);
				$return .= <<<HTML
				<div class="popup-form decent" id="edit-user-post">
					<form id="thread_form" method="post" action="{$this->core->buildUrl(array('component' => 'forum', 'page' => 'edit_post'))}">
					 <input type="hidden" id="edit_thread_id" name="edit_thread_id" value="" data-ays-ignore="true"/>
					 <input type="hidden" id="edit_post_id" name="edit_post_id" value="" data-ays-ignore="true"/>
					 <h3 id="edit_user_prompt"></h3>
HTML;
						$return .= $this->core->getOutput()->renderTwigTemplate("forum/ThreadPostForm.twig",[
								"show_title" => true,
								"show_post" => true,
								"show_categories" => true,
								"show_attachments" => false,
								"show_anon" => true,
								"show_announcement" => false,
								"show_editcat" => false,
								"show_cancel_edit_form" => true,
								"submit_label" => "Update Post",
							]);
						$return .= <<<HTML
					</form>
				</div>
HTML;
			}

			$return .= <<<HTML
				<div id="forum_wrapper">
					<div id="thread_list" class="thread_list">
HTML;
				$activeThreadAnnouncement = false;
				$activeThreadTitle = "";
				$function_date = 'date_format';
				$activeThread = array();
				$return .= $this->displayThreadList($threads, false, $activeThreadAnnouncement, $activeThreadTitle, $activeThread, $currentThread, $currentCategoriesIds);

					$activeThreadTitle = htmlentities(html_entity_decode($activeThreadTitle, ENT_QUOTES | ENT_HTML5, 'UTF-8'), ENT_QUOTES | ENT_HTML5, 'UTF-8');

			$thread_id = -1;
			$userAccessToAnon = ($this->core->getUser()->getGroup() < 4) ? true : false;
			$title_html = '';
			$return .= <<<HTML

					</div>
					<div style="display:inline-block;width:70%; float: right;" id="posts_list" class="posts_list">
HTML;

            $title_html .= <<<HTML
            <h3 style="max-width: 95%; display:inline-block;word-wrap: break-word;margin-top:10px; margin-left: 5px;">
HTML;
					if($this->core->getUser()->getGroup() <= 2 && $activeThreadAnnouncement){
                        $title_html .= <<<HTML
							<a style="display:inline-block; color:orange; " onClick="alterAnnouncement({$activeThread['id']}, 'Are you sure you want to remove this thread as an announcement?', 'remove_announcement')" title="Remove thread from announcements"><i class="fa fa-star" onmouseleave="changeColor(this, 'gold')" onmouseover="changeColor(this, '#e0e0e0')" style="position:relative; display:inline-block; color:gold; -webkit-text-stroke-width: 1px;
    -webkit-text-stroke-color: black;" aria-hidden="true"></i></a>
HTML;
                    } else if($activeThreadAnnouncement){
                        $title_html .= <<<HTML
						 <i class="fa fa-star" style="position:relative; display:inline-block; color:gold; -webkit-text-stroke-width: 1px; -webkit-text-stroke-color: black;" aria-hidden="true"></i>
HTML;
                    } else if($this->core->getUser()->getGroup() <= 2 && !$activeThreadAnnouncement){
                        $title_html .= <<<HTML
							<a style="position:relative; display:inline-block; color:orange; " onClick="alterAnnouncement({$activeThread['id']}, 'Are you sure you want to make this thread an announcement?', 'make_announcement')" title="Make thread an announcement"><i class="fa fa-star" onmouseleave="changeColor(this, '#e0e0e0')" onmouseover="changeColor(this, 'gold')" style="position:relative; display:inline-block; color:#e0e0e0; -webkit-text-stroke-width: 1px;
    -webkit-text-stroke-color: black;" aria-hidden="true"></i></a>
HTML;
                    }
                    if(isset($activeThread['favorite']) && $activeThread['favorite']) {
                    	$title_html .= <<<HTML
							<a style="position:relative; display:inline-block; color:orange; " onClick="pinThread({$activeThread['id']}, 'unpin_thread');" title="Pin Thread"><i class="fa fa-thumb-tack" onmouseleave="changeColor(this, 'gold')" onmouseover="changeColor(this, '#e0e0e0')" style="position:relative; display:inline-block; color:gold; -webkit-text-stroke-width: 1px;-webkit-text-stroke-color: black;" aria-hidden="true"></i></a>
HTML;
					} else {
                    	$title_html .= <<<HTML
							<a style="position:relative; display:inline-block; color:orange; " onClick="pinThread({$activeThread['id']}, 'pin_thread');" title="Pin Thread"><i class="fa fa-thumb-tack" onmouseleave="changeColor(this, '#e0e0e0')" onmouseover="changeColor(this, 'gold')" style="position:relative; display:inline-block; color:#e0e0e0; -webkit-text-stroke-width: 1px;-webkit-text-stroke-color: black;" aria-hidden="true"></i></a>
HTML;
					}
                    $title_html .= <<< HTML
					{$activeThreadTitle}</h3>
HTML;
					$first = true;
					$first_post_id = 1;
					if($display_option == "tree"){
						$order_array = array();
						$reply_level_array = array();
						foreach($posts as $post){
							if($thread_id == -1) {
								$thread_id = $post["thread_id"];
							}
							if($first){
								$first= false;
								$first_post_id = $post["id"];
							}
							if($post["parent_id"] > $first_post_id){
								$place = array_search($post["parent_id"], $order_array);
								$tmp_array = array($post["id"]);
								$parent_reply_level = $reply_level_array[$place];
								while($place && $place+1 < sizeof($reply_level_array) && $reply_level_array[$place+1] > $parent_reply_level){
									$place++;
								}
								array_splice($order_array, $place+1, 0, $tmp_array);
								array_splice($reply_level_array, $place+1, 0, $parent_reply_level+1);
							} else {
								array_push($order_array, $post["id"]);
								array_push($reply_level_array, 1);
							}
						}
						$i = 0;
						$first = true;
						foreach($order_array as $ordered_post){
							foreach($posts as $post){
								if($post["id"] == $ordered_post){
									if($post["parent_id"] == $first_post_id) {
										$reply_level = 1;	
									} else {
										$reply_level = $reply_level_array[$i];
									}
										
									$return .= $this->createPost($thread_id, $post, $function_date, $title_html, $first, $reply_level, $display_option);
									break;
								}						
							}
							if($first){
								$first= false;
							}
							$i++;
						}	
					} else {
						foreach($posts as $post){
							if($thread_id == -1) {
								$thread_id = $post["thread_id"];
							}
							$return .= $this->createPost($thread_id, $post, $function_date, $title_html, $first, 1, $display_option);		
							if($first){
								$first= false;
							}			
						}
					}
			$return .= <<<HTML

			<hr style="border-top:1px solid #999;margin-bottom: 5px;" />
			
					<form style="margin-right:17px;" method="POST" action="{$this->core->buildUrl(array('component' => 'forum', 'page' => 'publish_post'))}" enctype="multipart/form-data">
						<input type="hidden" name="thread_id" value="{$thread_id}" />
						<input type="hidden" name="parent_id" value="{$first_post_id}" />
						<input type="hidden" name="display_option" value="{$display_option}" />
	            		<br/>
	            		<div style="margin-bottom:10px;" class="form-group row">
            		<button type="button" title="Insert a link" onclick="addBBCode(1, '#post_content')" style="margin-right:10px;" class="btn btn-default">Link <i class="fa fa-link fa-1x"></i></button><button title="Insert a code segment" type="button" onclick="addBBCode(0, '#post_content')" class="btn btn-default">Code <i class="fa fa-code fa-1x"></i></button>
HTML;
					if($this->core->getUser()->getGroup() <= 2){
						$return .= <<<HTML
						<a class="btn btn-primary" style="position:relative;float:right;top:3px;display:inline-block;" title="Merge Threads" onclick="$('#merge-threads').css('display', 'block');">Merge Threads</a>
HTML;
					}
					$return .= <<<HTML
            	</div>
	            		<div class="form-group row">
	            			<textarea name="post_content" onclick="hideReplies();" id="post_content" class="post_content_reply" style="white-space: pre-wrap;resize:none;overflow:hidden;min-height:100px;width:100%;" rows="10" cols="30" placeholder="Enter your reply to all here..." required></textarea>
	            		</div>

	            		<br/>

	           			<span style="float:left;display:inline-block;">
            				<label id="file_input_label" class="btn btn-default" for="file_input">
    						<input id="file_input" name="file_input[]" accept="image/*" type="file" style="display:none" onchange="checkNumFilesForumUpload(this)" multiple>
    						Upload Attachment
							</label>
							<span class='label label-info' id="file_name"></span>
						</span>

	            		<div style="margin-bottom:20px;float:right;" class="form-group row">
	            			<label style="display:inline-block;" for="Anon">Anonymous (to class)?</label> <input type="checkbox" style="margin-right:15px;display:inline-block;" name="Anon" value="Anon" data-ays-ignore="true"/><input type="submit" style="display:inline-block;" name="post" value="Submit Reply to All" class="btn btn-primary" />
	            		</div>
	            	</form>
	            	<br/>

					</div>

				</div>
				</div>
HTML;
		}

		$return .= <<<HTML
	<script>
		var codeSegments = document.querySelectorAll("[id=code]");
		for (let element of codeSegments){
			var editor0 = CodeMirror.fromTextArea(element, {
            lineNumbers: true,
            readOnly: true,
            cursorHeight: 0.0,
            lineWrapping: true
	    });

	    var lineCount = editor0.lineCount();
	    if (lineCount == 1) {
	        editor0.setSize("100%", (editor0.defaultTextHeight() * 2) + "px");
	    }
	    else {
	        editor0.setSize("100%", "auto");
	    }
	    editor0.setOption("theme", "eclipse");
	    editor0.refresh(); 
		}
			
	    </script>
HTML;

		return $return;
	}

	public function showAlteredDisplayList($threads, $filtering, $thread_id, $categories_ids){
		$tempArray = array();
		$threadAnnouncement = false;
		$activeThreadTitle = "";
		return $this->displayThreadList($threads, $filtering, $threadAnnouncement, $activeThreadTitle, $tempArray, $thread_id, $categories_ids);
	}

	public function displayThreadList($threads, $filtering, &$activeThreadAnnouncement, &$activeThreadTitle, &$activeThread, $thread_id_p, $current_categories_ids){
					$return = "";
					$used_active = false; //used for the first one if there is not thread_id set
					$current_user = $this->core->getUser()->getId();
					$start = 0;
					$activeThreadAnnouncement = false;
					$activeThreadTitle = "";
					$function_date = 'date_format';
					$activeThread = array();
					$end = 10;
					foreach($threads as $thread){
						$first_post = $this->core->getQueries()->getFirstPostForThread($thread["id"]);
						$date = date_create($first_post['timestamp']);
						$class = "thread_box";
						// $current_categories_ids should be subset of $thread["categories_ids"]
						$issubset = (count(array_intersect($current_categories_ids, $thread["categories_ids"])) == count($current_categories_ids));
						if(((isset($_REQUEST["thread_id"]) && $_REQUEST["thread_id"] == $thread["id"]) || $thread_id_p == $thread["id"] || $thread_id_p == -1) && !$used_active && $issubset) {
							$class .= " active";
							$used_active = true;
							$activeThreadTitle = $thread["title"];
							$activeThread = $thread;
							if($thread["pinned"])
								$activeThreadAnnouncement = true;
							if($thread_id_p == -1)
								$thread_id_p = $thread["id"];
						}
						if($this->core->getQueries()->viewedThread($current_user, $thread["id"])){
							$class .= " viewed";
						}

						//fix legacy code
						$titleDisplay = html_entity_decode($thread['title'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
						$first_post_content = html_entity_decode($first_post['content'], ENT_QUOTES | ENT_HTML5, 'UTF-8');

						//replace tags from displaying in sidebar
						$first_post_content = str_replace("[/code]", "", str_replace("[code]", "", strip_tags($first_post["content"])));
						$temp_first_post_content = preg_replace('#\[url=(.*?)\](.*?)(\[/url\])#', '$2', $first_post_content);

						if(!empty($temp_first_post_content)){
							$first_post_content = $temp_first_post_content;
						}

						$sizeOfContent = strlen($first_post_content);
						$contentDisplay = substr($first_post_content, 0, ($sizeOfContent < 80) ? $sizeOfContent : strrpos(substr($first_post_content, 0, 80), " "));
						$titleLength = strlen($thread['title']);

						$titleDisplay = substr($titleDisplay, 0, ($titleLength < 40) ? $titleLength : strrpos(substr($titleDisplay, 0, 40), " "));

						if(strlen($first_post["content"]) > 80){
							$contentDisplay .= "...";
						}
						if(strlen($thread["title"]) > 40){
							//Fix ... appearing
							if(empty($titleDisplay))
								$titleDisplay .= substr($thread['title'], 0, 30);
							$titleDisplay .= "...";
						}
						$titleDisplay = htmlentities($titleDisplay, ENT_QUOTES | ENT_HTML5, 'UTF-8');
						$first_post_content = htmlentities($first_post_content, ENT_QUOTES | ENT_HTML5, 'UTF-8');
						$return .= <<<HTML
						<a href="{$this->core->buildUrl(array('component' => 'forum', 'page' => 'view_thread', 'thread_id' => $thread['id']))}">
						<div class="{$class}">
HTML;
						if($thread["pinned"] == true){
							$return .= <<<HTML
							<i class="fa fa-star" style="position:relative; float:right; display:inline-block; color:gold; -webkit-text-stroke-width: 1px;
    -webkit-text-stroke-color: black;" aria-hidden="true"></i>
HTML;
						}
						if(isset($thread['favorite']) && $thread['favorite']) {
							$return .= <<<HTML
							<i class="fa fa-thumb-tack" style="position:relative; float:right; display:inline-block; color:gold; -webkit-text-stroke-width: 1px;
    -webkit-text-stroke-color: black;" aria-hidden="true"></i>
HTML;
						}

						$categories_content = array();
						foreach ($thread["categories_desc"] as $category_desc) {
							$categories_content[] = array(htmlentities($category_desc, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
						}
						for ($i = 0; $i < count($thread["categories_color"]); $i+=1) {
							$categories_content[$i][] = $thread["categories_color"][$i];
						}
						$return .= <<<HTML
						<h4>{$titleDisplay}</h4>
						<h5 style="font-weight: normal;">{$contentDisplay}</h5>
HTML;
						foreach ($categories_content as $category_content) {
							$return .= <<<HTML
							<span class="label_forum" style="background-color: {$category_content[1]}">{$category_content[0]}</span>
HTML;
						}
						$return .= <<<HTML
						<h5 style="float:right; font-weight:normal;margin-top:5px">{$function_date($date,"n/j g:i A")}</h5>
						</div>
						</a>
						<hr style="margin-top: 0px;margin-bottom:0px;">
HTML;
					}
					return $return;
	}

	public function createPost($thread_id, $post, $function_date, $title_html, $first, $reply_level, $display_option){
		$post_html = "";
		$post_id = $post["id"];
		$thread_dir = FileUtils::joinPaths(FileUtils::joinPaths($this->core->getConfig()->getCoursePath(), "forum_attachments"), $thread_id);

		$date = date_create($post["timestamp"]);
		$full_name = $this->core->getQueries()->getDisplayUserNameFromUserId($post["author_user_id"]);
		$first_name = htmlentities(trim($full_name["first_name"]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
		$last_name = htmlentities(trim($full_name["last_name"]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
		$visible_username = $first_name . " " . substr($last_name, 0 , 1) . ".";


		if($display_option != 'tree'){
			$reply_level = 1;
		}
		
		if($post["anonymous"]){
			$visible_username = "Anonymous";
		} 
		$classes = "post_box";						
		if($first && $display_option != 'alpha'){
			$classes .= " first_post";
		}

		if($this->core->getQueries()->isStaffPost($post["author_user_id"])){
			$classes .= " important";
		}
		$offset = min(($reply_level - 1) * 30, 180);
		
		$return = <<<HTML
			<div class="$classes" id="$post_id" style="margin-left:{$offset}px;" reply-level="$reply_level">
HTML;


		if($first){
            $return .= $title_html;
        } 

        //handle converting links 


        //convert legacy htmlentities being saved in db
        $post_content = html_entity_decode($post["content"], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $pre_post = preg_replace('#(<a href=[\'"])(.*?)([\'"].*>)(.*?)(</a>)#', '[url=$2]$4[/url]', $post_content);

        if(!empty($pre_post)){
            $post_content = $pre_post;
        }
			
		$post_content = htmlentities($post_content, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        preg_match_all('#\&lbrack;url&equals;(.*?)&rsqb;(.*?)(&lbrack;&sol;url&rsqb;)#', $post_content, $result);
        $accepted_schemes = array("https", "http");
        $pos = 0;
        if(count($result) > 0) {
            foreach($result[1] as $url){
                $decoded_url = filter_var(trim(strip_tags(html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8'))), FILTER_SANITIZE_URL);
                $parsed_url = parse_url($decoded_url, PHP_URL_SCHEME);
            	if(filter_var($decoded_url, FILTER_VALIDATE_URL, FILTER_FLAG_SCHEME_REQUIRED | FILTER_FLAG_HOST_REQUIRED) !== false && in_array($parsed_url, $accepted_schemes, true)){
                    $pre_post = preg_replace('#\&lbrack;url&equals;(.*?)&rsqb;(.*?)(&lbrack;&sol;url&rsqb;)#', '<a href="' . htmlspecialchars($decoded_url, ENT_QUOTES) . '" target="_blank" rel="noopener nofollow">'. $result[2][$pos] .'</a>', $post_content, 1);
                } else {
            		$pre_post = preg_replace('#\&lbrack;url&equals;(.*?)&rsqb;(.*?)(&lbrack;&sol;url&rsqb;)#', htmlentities(htmlspecialchars($decoded_url), ENT_QUOTES | ENT_HTML5, 'UTF-8'), $post_content, 1);
                }
                if(!empty($pre_post)){
                	$post_content = $pre_post;
				} 
				$pre_post = "";
                $pos++;
            }
        }
        //This code is for legacy posts that had an extra \r per newline
        if(strpos($post['content'], "\r") !== false){
            $post_content = str_replace("\r","", $post_content);
        }

        //end link handling

        //handle converting code segments

        $codeBracketString = "&lbrack;&sol;code&rsqb;";
        if(strpos($post_content, "&NewLine;&lbrack;&sol;code&rsqb;") !== false){
            $codeBracketString = "&NewLine;" . $codeBracketString;
        }

        $post_content = str_replace($codeBracketString, '</textarea>', str_replace('&lbrack;code&rsqb;', '<textarea id="code">', $post_content));

		//end code segment handling
		$return .= <<<HTML
			<pre><p class="post_content" style="white-space: pre-wrap; ">{$post_content}</p></pre>		
			<hr style="margin-bottom:3px;">
HTML;
		if($display_option == 'tree'){
			if(!$first){
				$return .= <<<HTML
					<a class="btn btn-default btn-sm" style=" text-decoration: none;" onClick="replyPost({$post['id']})"> Reply</a>
HTML;
			} else {
				$return .= <<<HTML
					<a class="btn btn-default btn-sm" style=" text-decoration: none;" onClick="$('html, .posts_list').animate({ scrollTop: document.getElementById('posts_list').scrollHeight }, 'slow');"> Reply</a>
HTML;
			}
		}
		$return .= <<<HTML
			<span style="margin-top:8px;margin-left:10px;float:right;">							
HTML;

		if($this->core->getUser()->getGroup() <= 2){
			$info_name = $first_name . " " . $last_name . " (" . $post['author_user_id'] . ")";
			$visible_user_json = json_encode($visible_username);
			$info_name = json_encode($info_name);
			$jscriptAnonFix = $post['anonymous'] ? 'true' : 'false' ;
			$jscriptAnonFix = json_encode($jscriptAnonFix);
			$return .= <<<HTML
				<a style=" margin-right:2px;display:inline-block; color:black; " onClick='changeName(this.parentNode, {$info_name}, {$visible_user_json}, {$jscriptAnonFix})' title="Show full user information"><i class="fa fa-eye" aria-hidden="true"></i></a>
HTML;
}
		if(!$first){
			$return .= <<<HTML
				<a class="expand btn btn-default btn-sm" style="float:right; text-decoration:none; margin-top: -8px" onClick="hidePosts(this, {$post['id']})"></a>
HTML;
		}
		if($this->core->getUser()->getGroup() <= 2){
			$wrapped_content = json_encode($post['content']);
			$shouldEditThread = null;
			$edit_button_title = "";
			if($first) {
				$shouldEditThread = "true";
				$edit_button_title = "Edit thread and post";
			} else {
				$shouldEditThread = "false";
				$edit_button_title = "Edit post";
			}
			$return .= <<<HTML
				<a class="post_button" style="bottom: 1px;position:relative; display:inline-block; color:red; float:right;" onClick="deletePost( {$post['thread_id']}, {$post['id']}, '{$post['author_user_id']}', '{$function_date($date,'n/j g:i A')}' )" title="Remove post"><i class="fa fa-times" aria-hidden="true"></i></a>
				<a class="post_button" style="position:relative; display:inline-block; color:black; float:right;" onClick="editPost({$post['id']}, {$post['thread_id']}, {$shouldEditThread})" title="{$edit_button_title}"><i class="fa fa-edit" aria-hidden="true"></i></a>
HTML;
		} 

		$return .= <<<HTML
		<h7 style="position:relative; right:5px;"><strong id="post_user_id">{$visible_username}</strong> {$function_date($date,"n/j g:i A")} </h7></span>
HTML;

		if($post["has_attachment"]){
			$post_dir = FileUtils::joinPaths($thread_dir, $post["id"]);
			$files = FileUtils::getAllFiles($post_dir);
			foreach($files as $file){
				$path = rawurlencode($file['path']);
				$name = rawurlencode($file['name']);
				$name_display = htmlentities(rawurldecode($file['name']), ENT_QUOTES | ENT_HTML5, 'UTF-8');
				$return .= <<<HTML
					<a href="#" style="text-decoration:none;display:inline-block;white-space: nowrap;" class="btn-default btn-sm" onclick="openFile('forum_attachments', '{$name}', '{$path}')" > {$name_display} </a>
HTML;
			}					
		}
			$offset = $offset + 30;
						$return .= <<<HTML
</div>

           	<form class="reply-box" id="$post_id-reply" style="margin-left:{$offset}px" method="POST" action="{$this->core->buildUrl(array('component' => 'forum', 'page' => 'publish_post'))}" enctype="multipart/form-data">
						<input type="hidden" name="thread_id" value="{$thread_id}" />
						<input type="hidden" name="parent_id" value="{$post_id}" />
	            		<br/>

	            		<div style="margin-bottom:10px;" class="form-group row">
            				<button type="button" title="Insert a link" onclick="addBBCode(1, '#post_content_{$post_id}')" style="margin-right:10px;" class="btn btn-default">Link <i class="fa fa-link fa-1x"></i></button><button title="Insert a code segment" type="button" onclick="addBBCode(0, '#post_content_{$post_id}')" class="btn btn-default">Code <i class="fa fa-code fa-1x"></i></button>
            			</div>
	            		<div class="form-group row">
	            			<textarea name="post_content_{$post_id}" id="post_content_{$post_id}" class="post_content_reply" style="white-space: pre-wrap;resize:none;overflow:hidden;min-height:100px;width:100%;" rows="10" cols="30" placeholder="Enter your reply to {$visible_username} here..." required></textarea>
	            		</div>

	            		<br/>

	           			<span style="float:left;display:inline-block;">
            				<label id="file_input_label_{$post_id}" class="btn btn-default" for="file_input_{$post_id}">
    						<input id="file_input_{$post_id}" name="file_input_{$post_id}[]" accept="image/*" type="file" style="display:none" onchange="checkNumFilesForumUpload(this, '{$post_id}')" multiple>
    						Upload Attachment
							</label>
							<span class='label label-info' id="file_name_{$post_id}"></span>
						</span>

	            		<div style="margin-bottom:20px;float:right;" class="form-group row">
	            			<label style="display:inline-block;" for="Anon">Anonymous (to class)?</label> <input type="checkbox" style="margin-right:15px;display:inline-block;" name="Anon" value="Anon" data-ays-ignore="true"/><input type="submit" style="display:inline-block;" name="post" value="Submit Reply to {$visible_username}" class="btn btn-primary" />
	            		</div>
	            	</form>
HTML;

		return $return;
	}

	public function createThread($category_colors) {

		if(!$this->forumAccess()){
			$this->core->redirect($this->core->buildUrl(array('component' => 'navigation')));
			return;
		}

		$this->core->getOutput()->addBreadcrumb("Discussion Forum", $this->core->buildUrl(array('component' => 'forum', 'page' => 'view_thread')));
		$this->core->getOutput()->addBreadcrumb("Create Thread", $this->core->buildUrl(array('component' => 'forum', 'page' => 'create_thread')));
		$return = <<<HTML
		<script type="text/javascript" language="javascript" src="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.6.0/Sortable.min.js"></script>
		<script type="text/javascript" language="javascript" src="https://cdnjs.cloudflare.com/ajax/libs/jquery.AreYouSure/1.9.0/jquery.are-you-sure.min.js"></script>

		<script> 
			$( document ).ready(function() {
			    enableTabsInTextArea('#thread_post_content');
				$("form").areYouSure();
			});
		 </script>

		<div style="margin-top:5px;background-color:transparent; margin: !important auto;padding:0px;box-shadow: none;" class="content">
HTML;
		if($this->core->getUser()->getGroup() <= 2){
			$categories = $this->core->getQueries()->getCategories();
			$return .= <<<HTML
			<div class="popup-form" id="category-list">
				<h3>Categories</h3>
				<span  style="float: right;">
					<input id="new_category_text" placeholder="New Category" style="resize:none;" rows="1" type="text" name="new_category" id="new_category" />
					<button type="button" title="Add new category" onclick="addNewCategory();" style="margin-left:10px;" class="btn btn-primary btn-sm">
						<i class="fa fa-plus-circle fa-1x"></i> Add category
					</button>
				</span>
				<pre>(Drag to re-order)</pre><br>
HTML;
				if(count($categories) == 0) {
					$return .= <<<HTML
					<span class='category-list-no-element' style="margin-left: 1em;" >
						No categories exists please create one.
					</span>
HTML;
				}

				$dummy_category = array('color' => '#000000', 'category_desc' => 'dummy', 'category_id' => "dummy");
				array_unshift($categories, $dummy_category);

				$return .= <<<HTML
				<ul id='ui-category-list' style="padding-left: 1em;">
HTML;
				// TODO: scrollbar
				for($i = 0; $i < count($categories); $i++){
						$additional_display = "";
						$additional_class = "category-sortable";
						if($i==0) {
							// Dummy Category: On new category creation copy of dummy element will be append.
							$additional_display = "display: none;";
							$additional_class = "";
						}
						$return .= <<<HTML
						<li id="categorylistitem-{$categories[$i]['category_id']}" class="{$additional_class}" style="color: {$categories[$i]['color']};{$additional_display}">
							<i class="fa fa-bars handle" aria-hidden="true" title="Drag to reorder"></i>
							<span class="categorylistitem-desc">
								<span>{$categories[$i]['category_desc']}</span>
								<a class="post_button" title="Edit Category Description"><i class="fa fa-edit" aria-hidden="true"></i></a>
							</span>
							<span class="categorylistitem-editdesc" style="display: none;">
								<input type="text" placeholder="New Description of Category" style="padding: 0;">
								<a class="post_button" title="Save Changes"><i class="fa fa-check" aria-hidden="true"></i></a>
								<a class="post_button" title="Cancel Changes"><i class="fa fa-times" aria-hidden="true"></i></a>
							</span>
							<div style="float: right;width: auto;">
							<select class='category-color-picker' style="color: white;font-size: 14px;height: 18px;padding: 0px;">
HTML;
							foreach ($category_colors as $color_name => $color_code) {
								$selected = "";
								if($color_code == $categories[$i]['color']) {
									$selected = 'selected="selected"';
								}
								$return .= <<<HTML
								<option value="{$color_code}" style="color: white;background-color: {$color_code};" {$selected}>{$color_name}</option>
HTML;
							}
							$return .= <<<HTML
							</select>
							&nbsp;
							<a class="post_button" title="Delete Category"><i class="fa fa-trash" aria-hidden="true"></i></a>
							</div>
						</li>
HTML;
				}
				$return .= <<<HTML
				</ul>
				<div  style="width: 100%; margin-top: 10px;">
					<a style="float: right;" onclick="$('#ui-category-list').find('.fa-times').click();$('#category-list').css('display', 'none');" class="btn btn-danger">Close</a>
				</div>
				<script type="text/javascript">
					$(function() {
						$("#ui-category-list").sortable({
							items : '.category-sortable',
							handle: ".handle",
							update: function (event, ui) {
						        reorderCategories();
						    }
						});
						$("#ui-category-list").find(".fa-trash").click(function() {
							var item = $(this).parent().parent().parent();
							var category_id = parseInt(item.attr('id').split("-")[1]);
							var category_desc = item.find(".categorylistitem-desc span").text().trim();
							deleteCategory(category_id, category_desc);
						});
						$("#ui-category-list").find(".fa-edit").click(function() {
							var item = $(this).parent().parent().parent();
							var category_desc = item.find(".categorylistitem-desc span").text().trim();
							item.find(".categorylistitem-editdesc input").val(category_desc);
							item.find(".categorylistitem-desc").hide();
							item.find(".categorylistitem-editdesc").show();

						});
						$("#ui-category-list").find(".fa-times").click(function() {
							var item = $(this).parent().parent().parent();
							item.find(".categorylistitem-editdesc").hide();
							item.find(".categorylistitem-desc").show();
						});

						$("#ui-category-list").find(".fa-check").click(function() {
							var item = $(this).parent().parent().parent();
							var category_id = parseInt(item.attr('id').split("-")[1]);
							var category_desc_original = item.find(".categorylistitem-desc span").text().trim();
							var category_desc = item.find("input").val().trim();
							if(category_desc != category_desc_original) {
								editCategory(category_id, category_desc, null);
							}
							item.find(".categorylistitem-editdesc").hide();
							item.find(".categorylistitem-desc").show();
						});
						var refresh_color_select = function(element) {
							$(element).css("background-color",$(element).val());
						}
						$(".category-color-picker").change(function(color) {
							var category_id = parseInt($(this).parent().parent().attr('id').split("-")[1]);
							var category_color = $(this).val();
							editCategory(category_id, null, category_color);
							refresh_color_select($(this));
						});
						$(".category-color-picker").each(function(){
							refresh_color_select($(this));
						});
					});
				</script>

			</div>

HTML;
		}
		$return .= <<<HTML
		<div style="background-color: #E9EFEF; box-shadow:0 2px 15px -5px #888888;margin-top:10px;margin-left:20px;margin-right:20px;border-radius:3px; height:40px; margin-bottom:10px;" id="forum_bar">

		<a class="btn btn-primary" style="position:relative;top:3px;left:5px;" title="Back to threads" href="{$this->core->buildUrl(array('component' => 'forum', 'page' => 'view_thread'))}"><i class="fa fa-arrow-left"></i> Back to Threads</a>
HTML;

		if($this->core->getUser()->getGroup() <= 2){
			$return .= <<<HTML
			<a class="btn btn-primary" style="margin-left:10px;position:relative;top:3px;right:5px;display:inline-block;" title="Show Stats" onclick="resetScrollPosition();" href="{$this->core->buildUrl(array('component' => 'forum', 'page' => 'show_stats'))}">Stats</a>
HTML;
		}

		$return .= <<<HTML

			<form style="float:right;position:relative;top:3px;right:5px;display:inline-block;" method="post" action="{$this->core->buildUrl(array('component' => 'forum', 'page' => 'search_threads'))}">
			<input type="text" size="35" placeholder="search" name="search_content" id="search_content" required/>
			<button type="submit" name="search" title="Submit search" class="btn btn-primary">
  				<i class="fa fa-search"></i> Search
			</button>
			</form>
		</div>

		<div style="padding-left:20px;padding-top:1vh; padding-bottom: 10px;height:69vh;border-radius:3px;box-shadow: 0 2px 15px -5px #888888;padding-right:20px;background-color: #E9EFEF;" id="forum_wrapper">

		<h3> Create Thread </h3>

			<form id="thread_form" style="padding-right:15px;margin-top:15px;margin-left:10px;height:63vh;overflow-y: auto" method="POST" action="{$this->core->buildUrl(array('component' => 'forum', 'page' => 'publish_thread'))}" enctype="multipart/form-data">
HTML;
				$return .= $this->core->getOutput()->renderTwigTemplate("forum/ThreadPostForm.twig", [
					"show_title" => true,
					"show_post" => true,
					"show_categories" => true,
					"show_attachments" => true,
					"show_anon" => true,
					"show_announcement" => true,
					"show_editcat" => true,
					"show_cancel_edit_form" => false,
					"submit_label" => "Submit Post",
				]);
			$return .= <<<HTML
			</form>
		</div>
		</div>
HTML;

		return $return;
	}



	public function statPage($users) {

		if(!$this->forumAccess() || $this->core->getUser()->getGroup() > 2){
			$this->core->redirect($this->core->buildUrl(array('component' => 'navigation')));
			return;
		}

		$return = <<<HTML
		<div style="margin-left:20px;margin-top:10px; height:50px;" id="forum_bar">
			<a class="btn btn-primary" style="border:3px solid #E9EFEF" title="Back to threads" href="{$this->core->buildUrl(array('component' => 'forum', 'page' => 'view_thread'))}"><i class="fa fa-arrow-left"></i> Back to Threads</a>
		</div>
			<div style="padding-left:20px;padding-top:1vh; padding-bottom: 10px;border-radius:3px;box-shadow: 0 2px 15px -5px #888888;padding-right:20px;background-color: #E9EFEF;">
				<table class="table table-striped table-bordered persist-area" id="forum_stats_table">
					<tr>			
				        <td style = "cursor:pointer;" width="15%" id="user_down">User &darr;</td>
				        <td style = "cursor:pointer;" width="15%" id="total_posts_down">Total Posts (not deleted)</td>
				        <td style = "cursor:pointer;" width="15%" id="total_threads_down">Total Threads</td>
				        <td style = "cursor:pointer;" width="15%" id="total_deleted_down">Total Deleted Posts</td>
				        <td width="40%">Show Posts</td>
					</tr>
HTML;
		foreach($users as $user => $details){
			$first_name = $details["first_name"];
			$last_name = $details["last_name"];
			$post_count = count($details["posts"]);
			$posts = htmlspecialchars(json_encode($details["posts"]), ENT_QUOTES, 'UTF-8');
			$ids = htmlspecialchars(json_encode($details["id"]), ENT_QUOTES, 'UTF-8');
			$timestamps = htmlspecialchars(json_encode($details["timestamps"]), ENT_QUOTES, 'UTF-8');
			$thread_ids = htmlspecialchars(json_encode($details["thread_id"]), ENT_QUOTES, 'UTF-8');
			$thread_titles = htmlspecialchars(json_encode($details["thread_title"]), ENT_QUOTES, 'UTF-8');
			$num_deleted = ($details["num_deleted_posts"]);
			$return .= <<<HTML
			<tbody>
				<tr>
					<td>{$last_name}, {$first_name}</td>
					<td>{$post_count}</td>
					<td>{$details["total_threads"]}</td>
					<td>{$num_deleted}</td>
					<td><button class="btn btn-default" data-action = "expand" data-posts="{$posts}" data-id="{$ids}" data-timestamps="{$timestamps}" data-thread_id="{$thread_ids}" data-thread_titles="{$thread_titles}">Expand</button></td>
				</tr>
			</tbody>
HTML;
			
		}
		
		$return .= <<<HTML
				</table>
			</div>

			<script>
				$("td").click(function(){
					if($(this).attr('id')=="user_down"){
						sortTable(0);
					}
					if($(this).attr('id')=="total_posts_down"){
						sortTable(1);
					}
					if($(this).attr('id')=="total_threads_down"){
						sortTable(2);
					}
					if($(this).attr('id')=="total_deleted_down"){
						sortTable(3);
					}
					
				});
				$("button").click(function(){
					
					var action = $(this).data('action');
					var posts = $(this).data('posts');
					var ids = $(this).data('id');
					var timestamps = $(this).data('timestamps');
					var thread_ids = $(this).data('thread_id');
					var thread_titles = $(this).data('thread_titles');
					if(action=="expand"){
						
						
						for(var i=0;i<posts.length;i++){
							var post_string = posts[i];
							post_string = escapeSpecialChars(post_string);
							var thread_title = thread_titles[i]["title"];
							thread_title = escapeSpecialChars(thread_title);
							$(this).parent().parent().parent().append('<tr id="'+ids[i]+'"><td></td><td>'+timestamps[i]+'</td><td style = "cursor:pointer;" data-type = "thread" data-thread_id="'+thread_ids[i]+'"><pre style="font-family: inherit;white-space: pre-wrap;">'+thread_title+'</pre></td><td colspan = "2" style = "cursor:pointer;" align = "left" data-type = "post" data-thread_id="'+thread_ids[i]+'"><pre style="font-family: inherit;white-space: pre-wrap;">'+post_string+'</pre></td></tr> ');
							
						}
						$(this).html("Collapse");
						$(this).data('action',"collapse");
						$("td").click(function(){
						
							if($(this).data('type')=="post" || $(this).data('type')=="thread"){
			
								var id = $(this).data('thread_id');
								var url = buildUrl({'component' : 'forum', 'page' : 'view_thread', 'thread_id' : id});
								window.open(url);
							}
						
					});
					}
					else{
						for(var i=0;i<ids.length;i++){
							var item = document.getElementById(ids[i]);
							item.remove();
						}
						
						$(this).html("Expand");
						$(this).data('action',"expand");
					}
					
					
					return false;
				});


				

				function sortTable(sort_element_index){
					var table = document.getElementById("forum_stats_table");
					var switching = true;
					while(switching){
						switching=false;
						var rows = table.getElementsByTagName("TBODY");
						for(var i=1;i<rows.length-1;i++){

							var a = rows[i].getElementsByTagName("TR")[0].getElementsByTagName("TD")[sort_element_index];
							var b = rows[i+1].getElementsByTagName("TR")[0].getElementsByTagName("TD")[sort_element_index];
							if(sort_element_index == 0 ? a.innerHTML>b.innerHTML : parseInt(a.innerHTML) < parseInt(b.innerHTML)){
								rows[i].parentNode.insertBefore(rows[i+1],rows[i]);
								switching=true;
							}
						}

					}

					var row0 = table.getElementsByTagName("TBODY")[0].getElementsByTagName("TR")[0];
					var headers = row0.getElementsByTagName("TD");
					
					for(var i = 0;i<headers.length;i++){
						var index = headers[i].innerHTML.indexOf(' ↓');
						
						if(index> -1){

							headers[i].innerHTML = headers[i].innerHTML.substr(0, index);
							break;
						}
					}

					headers[sort_element_index].innerHTML = headers[sort_element_index].innerHTML + ' ↓';

				}


			</script>
HTML;
		return $return;

	}

}

