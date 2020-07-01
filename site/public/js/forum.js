function categoriesFormEvents(){
    $("#ui-category-list").sortable({
        items : '.category-sortable',
        handle: ".handle",
        update: function (event, ui) {
            reorderCategories();
        }
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

    var refresh_color_select = function(element) {
        $(element).css("background-color",$(element).val());
    }

    $(".category-color-picker").each(function(){
        refresh_color_select($(this));
    });
}

function openFileForum(directory, file, path ){
    var url = buildCourseUrl(['display_file']) + '?dir=' + directory + '&file=' + file + '&path=' + path;
    window.open(url,"_blank","toolbar=no,scrollbars=yes,resizable=yes, width=700, height=600");
}

function checkForumFileExtensions(post_box_id, files){
    let count = files.length;
    for(let i = 0; i < files.length; i++) {
        let extension = getFileExtension(files[i].name);
        if( !['gif', 'png', 'jpg', 'jpeg', 'bmp'].includes(extension) ) {
            deleteSingleFile(files[i].name, post_box_id, false);
            removeLabel(files[i].name, post_box_id);
            files.splice(i, 1);
            i--;
        }
    } 
    return count == files.length;
}

function resetForumFileUploadAfterError(displayPostId){
    $('#file_name' + displayPostId).html('');
    document.getElementById('file_input_label' + displayPostId).style.border = "2px solid red";
    document.getElementById('file_input' + displayPostId).value = null;
}

function checkNumFilesForumUpload(input, post_id){
    var displayPostId = (typeof post_id !== "undefined") ? "_" + escapeSpecialChars(post_id) : "";
    if(input.files.length > 5){
        displayErrorMessage('Max file upload size is 5. Please try again.');
        resetForumFileUploadAfterError(displayPostId);
    }
    else {
        if(!checkForumFileExtensions(input.files)){
            displayErrorMessage('Invalid file type. Please upload only image files. (PNG, JPG, GIF, BMP...)');
            resetForumFileUploadAfterError(displayPostId);
            return;
        }
        $('#file_name' + displayPostId).html('<p style="display:inline-block;">' + input.files.length + ' files selected.</p>');
        $('#messages').fadeOut();
        document.getElementById('file_input_label' + displayPostId).style.border = "";
    }
}

function testAndGetAttachments(post_box_id, dynamic_check) {
    var index = post_box_id - 1;
    var files = [];
    for (var j = 0; j < file_array[index].length; j++) {
        if (file_array[index][j].name.indexOf("'") != -1 ||
            file_array[index][j].name.indexOf("\"") != -1) {
            alert("ERROR! You may not use quotes in your filename: " + file_array[index][j].name);
            return false;
        }
        else if (file_array[index][j].name.indexOf("\\\\") != -1 ||
            file_array[index][j].name.indexOf("/") != -1) {
            alert("ERROR! You may not use a slash in your filename: " + file_array[index][j].name);
            return false;
        }
        else if (file_array[index][j].name.indexOf("<") != -1 ||
            file_array[index][j].name.indexOf(">") != -1) {
            alert("ERROR! You may not use angle brackets in your filename: " + file_array[index][j].name);
            return false;
        }
        files.push(file_array[index][j]);
    }
    
    var valid = true;
    if(!checkForumFileExtensions(post_box_id, files)){
        displayErrorMessage('Invalid file type. Please upload only image files. (PNG, JPG, GIF, BMP...)');
        valid = false;
    }

    if(files.length > 5){
        if(dynamic_check) {
            displayErrorMessage('Max file upload size is 5. Please remove attachments accordingly.');
        }
        else {
            displayErrorMessage('Max file upload size is 5. Please try again.');
        }
        valid = false;
    }

    if(!valid) {
        return false;
    }
    else {
        return files;
    }
}

function publishFormWithAttachments(form, test_category, error_message, is_thread) {
    if(!form[0].checkValidity()) {
        form[0].reportValidity();
        return false;
    }
    if(test_category) {

        if((!form.prop("ignore-cat")) && form.find('.btn-selected').length == 0 && ($('.cat-buttons input').is(":checked") == false)) {
            alert("At least one category must be selected.");
            return false;
        }
    }
    var post_box_id = form.find(".thread-post-form").data("post_box_id");
    var formData = new FormData(form[0]);

    var files = testAndGetAttachments(post_box_id, false);
    if(files === false) {
        return false;
    }
    for(var i = 0; i < files.length ; i++) {
        formData.append('file_input[]', files[i], files[i].name);
    }
    var submit_url = form.attr('action');

    $.ajax({
        url: submit_url,
        data: formData,
        processData: false,
        contentType: false,
        type: 'POST',
        success: function(data){
            try {
                var json = JSON.parse(data);

                if(json["status"] === 'fail') {
                    var message ='<div class="inner-message alert alert-error" style="position: fixed;top: 40px;left: 50%;width: 40%;margin-left: -20%;" id="theid"><a class="fas fa-times message-close" onClick="removeMessagePopup(\'theid\');"></a><i class="fas fa-times-circle"></i>' + json["message"] + '</div>';
                    $('#messages').append(message);
                    return;
                }
            } catch (err){
                var message ='<div class="inner-message alert alert-error" style="position: fixed;top: 40px;left: 50%;width: 40%;margin-left: -20%;" id="theid"><a class="fas fa-times message-close" onClick="removeMessagePopup(\'theid\');"></a><i class="fas fa-times-circle"></i>Error parsing data. Please try again.</div>';
                $('#messages').append(message);
                return;
            }

            if (is_thread){
              var thread_id = json['data']['thread_id'];
              var course = document.body.dataset.courseUrl.split('/').pop();
              window.socketClient.send({'course': course, 'type': "new_thread", 'thread_id': thread_id});
            }
            window.location.href = json['data']['next_page'];
        },
        error: function(){
            var message ='<div class="inner-message alert alert-error" style="position: fixed;top: 40px;left: 50%;width: 40%;margin-left: -20%;" id="theid"><a class="fas fa-times message-close" onClick="removeMessagePopup(\'theid\');"></a><i class="fas fa-times-circle"></i>' + error_message + '</div>';
            $('#messages').append(message);
            return;
        }
    });
    return false;
}

function createThread(e) {
    e.preventDefault();
    try {
        return publishFormWithAttachments($(this), true, "Something went wrong while creating thread. Please try again.", true);
    }
    catch (err) {
        console.error(err);
        alert("Something went wrong. Please try again.");
        return false;
    }
}

function publishPost(e) {
    e.preventDefault();
    try {
        return publishFormWithAttachments($(this), false, "Something went wrong while publishing post. Please try again.", false);
    }
    catch (err) {
        console.error(err);
        alert("Something went wrong. Please try again.");
        return false;
    }
}

function socketNewThreadHandler(thread_id){
  $.ajax({
    type: 'POST',
    url: buildCourseUrl(['forum', 'threads', 'single']),
    data: {'thread_id': thread_id, 'csrf_token': window.csrfToken},
    success: function (response) {
      try {
        var new_thread = JSON.parse(response).data;

        if ($(new_thread).find(".thread-announcement").length != 0) {
          var last_bookmarked_announcement = $('.thread-announcement').siblings('.thread-favorite').last().parent().parent();
          if (last_bookmarked_announcement.length != 0) {
            $(new_thread).insertAfter(last_bookmarked_announcement.next()).hide().fadeIn("slow");
          } else {
            $(new_thread).insertBefore($('.thread_box_link').first()).hide().fadeIn("slow");
          }
        }
        else {
          var last_announcement = $('.thread-announcement').last().parent().parent();
          var last_bookmarked = $('.thread-favorite').last().parent().parent();
          var last = last_bookmarked.length == 0 ? last_announcement : last_bookmarked;

          if (last.length == 0) {
            $(new_thread).insertBefore($('.thread_box_link').first()).hide().fadeIn("slow");
          } else {
            $(new_thread).insertAfter(last.next()).hide().fadeIn("slow");
          }
        }

        $('[data-thread_id="' + thread_id + '"] .thread_box').removeClass("active");
      } catch(err) {
        var message ='<div class="inner-message alert alert-error" style="position: fixed;top: 40px;left: 50%;width: 40%;margin-left: -20%;" id="theid"><a class="fas fa-times message-close" onClick="removeMessagePopup(\'theid\');"></a><i class="fas fa-times-circle"></i>Error parsing new thread. Please refresh the page.</div>';
        $('#messages').append(message);
        return;
      }
    },
    error: function (a, b) {
      window.alert('Something went wrong when adding new thread. Please refresh the page.');
    }
  });
}

function socketDeleteOrMergeThreadHandler(thread_id, merge=false, merge_thread_id=null){
  var thread_to_delete = "[data-thread_id='" + thread_id + "']";
  $(thread_to_delete).fadeOut("slow", function () {
    $(thread_to_delete).next().remove();
    $(thread_to_delete).remove();
  });

  if ($("#current-thread").val() == thread_id){
    if (merge){
      var new_url = buildCourseUrl(['forum', 'threads', merge_thread_id]);
    }
    else {
      var new_url = buildCourseUrl(['forum', 'threads']);
    }
    window.location.replace(new_url);
    return;
  }
  else if (merge && $("#current-thread").val() == merge_thread_id)
    // will be changed when posts work with sockets
    window.location.reload();
}

function socketResolveThreadHandler(thread_id){
  var icon_to_update = $("[data-thread_id='" + thread_id + "']").find("i.fa-question");
  $(icon_to_update).fadeOut(400, function () {
    $(icon_to_update).removeClass("fa-question thread-unresolved").addClass("fa-check thread-resolved").fadeIn(400);
  });
  $(icon_to_update).attr("title", "Thread Resolved");
  $(icon_to_update).attr("aria-label", "Thread Resolved");

  if ($("#current-thread").val() == thread_id){
    $("[title='Mark thread as resolved']").remove();
  }
}

function socketAnnounceThreadHandler(thread_id) {
  /*
  * 1. get announced thread with thread_id
  * 2. find correct new place according to the following order:
  *     announcements & pins --> announcements only --> pins only --> other
  *     each group should be sorted chronologically
  * 3. if thread is "active" thread update related elements
  * */
  var thread_to_announce = "[data-thread_id='" + thread_id + "']";
  var hr = $(thread_to_announce).next(); // saving the <hr> for inserting later below the thread div
  hr.remove(); // removing this sibling <hr>
  // if there exists other announcements
  if ($('.thread-announcement').length != 0) {
    // if thread to announce is already bookmarked
    if ($(thread_to_announce).find(".thread-favorite").length != 0) {
      // if there exists other bookmarked announcements
      if ($('.thread-announcement').siblings('.thread-favorite').length != 0) {
        // notice that ids in desc order are also in a chronological order (newest : oldest)
        // get announcement threads ids as an array -> [7, 6, 4, 3]
        var announced_pinned_threads_ids = $('.thread-announcement').siblings('.thread-favorite').parent().parent().map(function() {
          return Number($(this).attr("data-thread_id"));
        }).get();
        // look for thread to insert before -> thread_id 4 if inserting thread_id = 5
        for (let i=0; i<announced_pinned_threads_ids.length; i++){
          if (announced_pinned_threads_ids[i] < thread_id){
            var thread_to_insert_before = "[data-thread_id='" + announced_pinned_threads_ids[i] + "']";
            $(thread_to_announce).insertBefore($(thread_to_insert_before)).hide().fadeIn("slow");
            break;
          }

          // if last thread then insert after -> if inserting thread_id = 2
          if (i == announced_pinned_threads_ids.length-1){
            var thread_to_insert_after = "[data-thread_id='" + announced_pinned_threads_ids[i] + "']";
            $(thread_to_announce).insertAfter($(thread_to_insert_after).next()).hide().fadeIn("slow");
          }
        }
      }
      // no bookmarked announcements -> insert already-bookmarked new announcment at the beginning
      else {
        $(thread_to_announce).insertBefore($('.thread_box_link').first()).hide().fadeIn("slow");
      }
    }
    // thread to announce is not bookmarked
    else {
      // find announcements that are not bookmarked
      var announced_pinned_threads = $(".thread-announcement").siblings(".thread-favorite").parent().parent();
      var announced_only_threads = $(".thread-announcement").parent().parent().not(announced_pinned_threads);
      if (announced_only_threads.length != 0){
        var announced_only_threads_ids = $(announced_only_threads).map(function() {
          return Number($(this).attr("data-thread_id"));
        }).get();
        for (let i=0; i<announced_only_threads_ids.length; i++){
          if (announced_only_threads_ids[i] < thread_id){
            var thread_to_insert_before = "[data-thread_id='" + announced_only_threads_ids[i] + "']";
            $(thread_to_announce).insertBefore($(thread_to_insert_before)).hide().fadeIn("slow");
            break;
          }

          if (i == announced_only_threads_ids.length-1){
            var thread_to_insert_after = "[data-thread_id='" + announced_only_threads_ids[i] + "']";
            $(thread_to_announce).insertAfter($(thread_to_insert_after).next()).hide().fadeIn("slow");
          }
        }
      }
      // if all announcements are bookmarked -> insert new annoucement after the last one
      else {
        var thread_to_insert_after = announced_pinned_threads.last();
        $(thread_to_announce).insertAfter($(thread_to_insert_after).next()).hide().fadeIn("slow");
      }
    }
  }
  // no annoucements at all -> insert new announcement at the beginning
  else {
    $(thread_to_announce).insertBefore($('.thread_box_link').first()).hide().fadeIn("slow");
  }

  var announcement_icon = "<i class=\"fas fa-thumbtack thread-announcement\" title = \"Pinned to the top\" aria-label=\"Pinned to the top\"></i>";
  $(thread_to_announce).children().prepend(announcement_icon);
  $(hr).insertAfter($(thread_to_announce)); // insert <hr> right after thread div
  // if user's current thread is the one modified -> update
  if ($("#current-thread").val() == thread_id){
    // if is instructor
    var instructor_pin = $(".not-active-thread-announcement");
    if (instructor_pin.length){
      instructor_pin.removeClass(".not-active-thread-announcement").addClass("active-thread-remove-announcement");
      instructor_pin.attr("onClick", instructor_pin.attr("onClick").replace("1,", "0,").replace("pin this thread to the top?", "unpin this thread?"));
      instructor_pin.attr("title", "Unpin Thread");
      instructor_pin.attr("aria-label", "Unpin Thread");
      instructor_pin.children().removeClass("golden_hover").addClass("reverse_golden_hover");
    }
    else {
      announcement_icon = "<i class=\"fas fa-thumbtack active-thread-announcement\" title = \"Pinned Thread\" aria-label=\"Pinned Thread\"></i>";
      $("#posts_list").find("h2").prepend(announcement_icon);
    }
  }
}

function socketUnpinThreadHandler(thread_id) {
  var thread_to_unpin = "[data-thread_id='" + thread_id + "']";

  var hr = $(thread_to_unpin).next(); // saving the <hr> for inserting later below the thread div
  hr.remove(); // removing this sibling <hr>

  var not_pinned_threads = $(".thread_box").not($(".thread-announcement").parent()).parent();
  // if there exists other threads that are not pinned
  if (not_pinned_threads.length){
    // if thread is bookmarked
    if ($(thread_to_unpin).find(".thread-favorite").length != 0){
      // if there exists other threads that are bookmarked
      if (not_pinned_threads.find(".thread-favorite").length != 0){
        var bookmarked_threads_ids = not_pinned_threads.find(".thread-favorite").parent().parent().map(function() {
          return Number($(this).attr("data-thread_id"));
        }).get();

        for (let i=0; i<bookmarked_threads_ids.length; i++){
          if (bookmarked_threads_ids[i] < thread_id){
            var thread_to_insert_before = "[data-thread_id='" + bookmarked_threads_ids[i] + "']";
            $(thread_to_unpin).insertBefore($(thread_to_insert_before)).hide().fadeIn("slow");
            break;
          }

          if (i == bookmarked_threads_ids.length-1){
            var thread_to_insert_after = "[data-thread_id='" + bookmarked_threads_ids[i] + "']";
            $(thread_to_unpin).insertAfter($(thread_to_insert_after).next()).hide().fadeIn("slow");
          }
        }
      }
      // no other bookmarked threads -> insert thread at the beginning of not announced threads
      else {
        $(thread_to_unpin).insertBefore(not_pinned_threads.first()).hide().fadeIn("slow");
      }
    }
    // thread is not bookmarked
    else {
      // if there exists other threads that are neither bookmarked nor pinned
      var not_bookmarked_threads = not_pinned_threads.not($(".thread-favorite").parent().parent());
      if (not_bookmarked_threads.length){
        var not_bookmarked_threads_ids = not_bookmarked_threads.map(function() {
          return Number($(this).attr("data-thread_id"));
        }).get();

        for (let i=0; i<not_bookmarked_threads_ids.length; i++){
          if (not_bookmarked_threads_ids[i] < thread_id){
            var thread_to_insert_before = "[data-thread_id='" + not_bookmarked_threads_ids[i] + "']";
            $(thread_to_unpin).insertBefore($(thread_to_insert_before)).hide().fadeIn("slow");
            break;
          }

          if (i == not_bookmarked_threads_ids.length-1){
            var thread_to_insert_after = "[data-thread_id='" + not_bookmarked_threads_ids[i] + "']";
            $(thread_to_unpin).insertAfter($(thread_to_insert_after).next()).hide().fadeIn("slow");
          }
        }
      }
      // no other threads -> insert thread at the end
      else {
        var thread_to_insert_after = $(".thread_box").last().parent();
        $(thread_to_unpin).insertAfter($(thread_to_insert_after).next()).hide().fadeIn("slow");
      }
    }
  }
  // no unpinned threads -> insert thread at the end
  else {
    var thread_to_insert_after = $(".thread_box").last().parent();
    $(thread_to_unpin).insertAfter($(thread_to_insert_after).next()).hide().fadeIn("slow");
  }

  $(hr).insertAfter($(thread_to_unpin)); // insert <hr> right after thread div
  $(thread_to_unpin).find(".thread-announcement").remove();

  // if user's current thread is the one modified -> update
  if ($("#current-thread").val() == thread_id){
    // if is instructor
    var instructor_pin = $(".active-thread-remove-announcement");
    if (instructor_pin.length){
      instructor_pin.removeClass("active-thread-remove-announcement").addClass("not-active-thread-announcement");
      instructor_pin.attr("onClick", instructor_pin.attr("onClick").replace("0,", "1,").replace("unpin this thread?", "pin this thread to the top?"));
      instructor_pin.attr("title", "Make thread an announcement");
      instructor_pin.attr("aria-label", "Pin Thread");
      instructor_pin.children().removeClass("reverse_golden_hover").addClass("golden_hover");
    }
    else {
      $(".active-thread-announcement").remove();
    }
  }
}

function initSocketClient() {
  window.socketClient = new WebSocketClient();
  window.socketClient.onmessage = (msg) => {
    if (msg.course === document.body.dataset.courseUrl.split('/').pop()) {
      switch (msg.type) {
        case "new_thread":
          socketNewThreadHandler(msg.thread_id);
          break;
        case "delete_thread":
          socketDeleteOrMergeThreadHandler(msg.thread_id);
          break;
        case "resolve_thread":
          socketResolveThreadHandler(msg.thread_id);
          break;
        case "announce_thread":
          socketAnnounceThreadHandler(msg.thread_id);
          break;
        case "unpin_thread":
          socketUnpinThreadHandler(msg.thread_id);
          break;
        case "merge_thread":
          socketDeleteOrMergeThreadHandler(msg.thread_id, true, msg.merge_thread_id);
          break;
        default:
          console.log("Undefined message recieved.");
      }
      thread_post_handler();
      loadThreadHandler();
    }
  };
  window.socketClient.open();
}

function changeThreadStatus(thread_id) {
    var url = buildCourseUrl(['forum', 'threads', 'status']) + '?status=1';
    $.ajax({
        url: url,
        type: "POST",
        data: {
            thread_id: thread_id,
            csrf_token: csrfToken
        },
        success: function(data) {
            try {
                var json = JSON.parse(data);
            } catch(err) {
                var message ='<div class="inner-message alert alert-error" style="position: fixed;top: 40px;left: 50%;width: 40%;margin-left: -20%;" id="theid"><a class="fas fa-times message-close" onClick="removeMessagePopup(\'theid\');"></a><i class="fas fa-times-circle"></i>Error parsing data. Please try again.</div>';
                $('#messages').append(message);
                return;
            }
            if(json['status'] === 'fail') {
                var message ='<div class="inner-message alert alert-error" style="position: fixed;top: 40px;left: 50%;width: 40%;margin-left: -20%;" id="theid"><a class="fas fa-times message-close" onClick="removeMessagePopup(\'theid\');"></a><i class="fas fa-times-circle"></i>' + json['message'] + '</div>';
                $('#messages').append(message);
                return;
            }
            var course = document.body.dataset.courseUrl.split('/').pop();
            window.socketClient.send({'course': course, 'type': "resolve_thread", 'thread_id': thread_id});
            window.location.reload();
            var message ='<div class="inner-message alert alert-success" style="position: fixed;top: 40px;left: 50%;width: 40%;margin-left: -20%;" id="theid"><a class="fas fa-times message-close" onClick="removeMessagePopup(\'theid\');"></a><i class="fas fa-check-circle"></i>Thread marked as resolved.</div>';
            $('#messages').append(message);
        },
        error: function() {
            window.alert('Something went wrong when trying to mark this thread as resolved. Please try again.');
        }
    });
}

function editPost(post_id, thread_id, shouldEditThread, render_markdown, csrf_token) {
    if(!checkAreYouSureForm()) {
        return;
    }
    var form = $("#thread_form");
    var url = buildCourseUrl(['forum', 'posts', 'get']);
    $.ajax({
        url: url,
        type: "POST",
        data: {
            post_id: post_id,
            thread_id: thread_id,
            render_markdown: render_markdown,
            csrf_token: csrf_token
        },
        success: function(data){
            try {
                var json = JSON.parse(data);
            } catch (err){
                var message ='<div class="inner-message alert alert-error" style="position: fixed;top: 40px;left: 50%;width: 40%;margin-left: -20%;" id="theid"><a class="fas fa-times message-close" onClick="removeMessagePopup(\'theid\');"></a><i class="fas fa-times-circle"></i>Error parsing data. Please try again.</div>';
                $('#messages').append(message);
                return;
            }
            if(json['status'] === 'fail'){
                var message ='<div class="inner-message alert alert-error" style="position: fixed;top: 40px;left: 50%;width: 40%;margin-left: -20%;" id="theid"><a class="fas fa-times message-close" onClick="removeMessagePopup(\'theid\');"></a><i class="fas fa-times-circle"></i>' + json['message'] + '</div>';
                $('#messages').append(message);
                return;
            }
            json = json['data'];
            var post_content = json.post;
            var lines = post_content.split(/\r|\r\n|\n/).length;
            var anon = json.anon;
            var change_anon = json.change_anon;
            var user_id = escapeSpecialChars(json.user);
            var time = Date.parse(json.post_time);
            if(!time) {
                // Timezone suffix ":00" might be missing
                time = Date.parse(json.post_time+":00");
            }
            time = new Date(time);
            var categories_ids = json.categories_ids;
            var date = time.toLocaleDateString();
            time = time.toLocaleString('en-US', { hour: 'numeric', minute: 'numeric', hour12: true });
            var contentBox = form.find("[name=thread_post_content]")[0];
            contentBox.style.height = lines*14;
            var editUserPrompt = document.getElementById('edit_user_prompt');
            editUserPrompt.innerHTML = 'Editing a post by: ' + user_id + ' on ' + date + ' at ' + time;
            contentBox.value = post_content;
            document.getElementById('edit_post_id').value = post_id;
            document.getElementById('edit_thread_id').value = thread_id;
            if(change_anon) {
                $('#thread_post_anon_edit').prop('checked', anon);
            }
            else {
                $('label[for=Anon]').remove();
                $('#thread_post_anon_edit').remove();
            }
            $('#edit-user-post').css('display', 'block');
            captureTabInModal("edit-user-post");

            $(".cat-buttons input").prop('checked', false);

            if(json.markdown === true){
                $('#markdown_input_').val("1");
                $('#markdown_toggle_').addClass('markdown-active');
                $('#markdown_buttons_').show();
            }
            else{
                $('#markdown_input_').val("0");
                $('#markdown_toggle_').removeClass('markdown-active');
                $('#markdown_buttons_').hide();
            }

            // If first post of thread
            if(shouldEditThread) {
                var thread_title = json.title;
                var thread_lock_date =  json.lock_thread_date;
                var thread_status = json.thread_status;
                $("#title").prop('disabled', false);
                $(".edit_thread").show();
                $('#label_lock_thread').show();
                $("#title").val(thread_title);
                $("#thread_status").val(thread_status);
                $('#lock_thread_date').val(thread_lock_date);

                // Categories
                $(".cat-buttons").removeClass('btn-selected');
                $.each(categories_ids, function(index, category_id) {
                    var cat_input = $(".cat-buttons input[value="+category_id+"]");
                    cat_input.prop('checked', true);
                    cat_input.parent().addClass('btn-selected');
                });
                $(".cat-buttons").trigger("eventChangeCatClass");
                $("#thread_form").prop("ignore-cat",false);
                $("#category-selection-container").show();
                $("#thread_status").show();
            }
            else {
                $("#title").prop('disabled', true);
                $(".edit_thread").hide();
                $('#label_lock_thread').hide();
                $("#thread_form").prop("ignore-cat",true);
                $("#category-selection-container").hide();
                $("#thread_status").hide();
            }
        },
        error: function(){
            window.alert("Something went wrong while trying to edit the post. Please try again.");
        }
    });
}


function changeDisplayOptions(option){
    thread_id = $('#current-thread').val();
    document.cookie = "forum_display_option=" + option + ";";
    window.location.replace(buildCourseUrl(['forum', 'threads', thread_id]) + `?option=${option}`);
}

function readCategoryValues(){
    var categories_value = [];
    $('#thread_category button').each(function(){
        if($(this).data("btn-selected")==="true"){
            categories_value.push($(this).data("cat_id"));
        }
    });
    return categories_value;
}

function readThreadStatusValues(){
    var thread_status_value = [];
    $('#thread_status_select button').each(function(){
        if($(this).data("btn-selected")==="true"){
            thread_status_value.push($(this).data("sel_id"));
        }
    });
    return thread_status_value;
}

function dynamicScrollLoadPage(element, atEnd) {
    var load_page = $(element).data(atEnd?"next_page":"prev_page");
    if(load_page == 0) {
        return false;
    }
    if($(element).data("dynamic_lock_load")) {
        return null;
    }
    var load_page_callback;
    var load_page_fail_callback;
    var arrow_up = $(element).find(".fa-caret-up");
    var arrow_down = $(element).find(".fa-caret-down");
    var spinner_up = arrow_up.prev();
    var spinner_down = arrow_down.next();
    $(element).data("dynamic_lock_load", true);
    if(atEnd){
        arrow_down.hide();
        spinner_down.show();
        load_page_callback = function(content, count) {
            spinner_down.hide();
            arrow_down.before(content);
            if(count == 0) {
                // Stop further loads
                $(element).data("next_page", 0);
            }
            else {
                $(element).data("next_page", parseInt(load_page) + 1);
                arrow_down.show();
            }
            dynamicScrollLoadIfScrollVisible($(element));
        };
        load_page_fail_callback = function(content, count) {
            spinner_down.hide();
        };
    }
    else {
        arrow_up.hide();
        spinner_up.show();
        load_page_callback = function(content, count) {
            spinner_up.hide();
            arrow_up.after(content);
            if(count == 0) {
                // Stop further loads
                $(element).data("prev_page", 0);
            }
            else {
                var prev_page = parseInt(load_page) - 1;
                $(element).data("prev_page", prev_page);
                if(prev_page >= 1) {
                    arrow_up.show();
                }
            }
            dynamicScrollLoadIfScrollVisible($(element));
        };
        load_page_fail_callback = function(content, count) {
            spinner_up.hide();
        };
    }

    var urlPattern = $(element).data("urlPattern");
    var currentThreadId = $(element).data("currentThreadId",);
    var currentCategoriesId = $(element).data("currentCategoriesId",);
    var course = $(element).data("course",);

    var next_url = urlPattern.replace("{{#}}", load_page);

    var categories_value = readCategoryValues();
    var thread_status_value = readThreadStatusValues();

    // var thread_status_value = $("#thread_status_select").val();
    var unread_select_value = $("#unread").is(':checked');
    categories_value = (categories_value == null)?"":categories_value.join("|");
    thread_status_value = (thread_status_value == null)?"":thread_status_value.join("|");
    $.ajax({
        url: next_url,
        type: "POST",
        data: {
            thread_categories: categories_value,
            thread_status: thread_status_value,
            unread_select: unread_select_value,
            currentThreadId: currentThreadId,
            currentCategoriesId: currentCategoriesId,
            csrf_token: window.csrfToken
        },
        success: function(r){
            var x = JSON.parse(r)['data'];
            var content = x.html;
            var count = x.count;
            content = `${content}`;
            $(element).data("dynamic_lock_load", false);
            load_page_callback(content, count);
        },
        error: function(){
            $(element).data("dynamic_lock_load", false);
            load_page_fail_callback();
            window.alert("Something went wrong while trying to load more threads. Please try again.");
        }
    });
    return true;
}

function dynamicScrollLoadIfScrollVisible(jElement) {
    if(jElement[0].scrollHeight <= jElement[0].clientHeight) {
        if(dynamicScrollLoadPage(jElement[0], true) === false) {
            dynamicScrollLoadPage(jElement[0], false);
        }
    }
}

function dynamicScrollContentOnDemand(jElement, urlPattern, currentThreadId, currentCategoriesId, course) {
    jElement.data("urlPattern",urlPattern);
    jElement.data("currentThreadId", currentThreadId);
    jElement.data("currentCategoriesId", currentCategoriesId);
    jElement.data("course", course);

    dynamicScrollLoadIfScrollVisible(jElement);
    $(jElement).scroll(function(){
        var element = $(this)[0];
        var sensitivity = 2;
        var isTop = element.scrollTop < sensitivity;
        var isBottom = (element.scrollHeight - element.offsetHeight - element.scrollTop) < sensitivity;
        if(isTop) {
            element.scrollTop = sensitivity;
            dynamicScrollLoadPage(element,false);
        }
        else if(isBottom) {
            dynamicScrollLoadPage(element,true);
        }

    });
}

function resetScrollPosition(id){
    if(sessionStorage.getItem(id+"_scrollTop") != 0) {
        sessionStorage.setItem(id+"_scrollTop", 0);
    }
}

function saveScrollLocationOnRefresh(id){
    var element = document.getElementById(id);
    $(element).scroll(function() {
        sessionStorage.setItem(id+"_scrollTop", $(element).scrollTop());
    });
    $(document).ready(function() {
        if(sessionStorage.getItem(id+"_scrollTop") !== null){
            $(element).scrollTop(sessionStorage.getItem(id+"_scrollTop"));
        }
    });
}

function checkAreYouSureForm() {
    var elements = $('form');
    if(elements.hasClass('dirty')) {
        if(confirm("You have unsaved changes! Do you want to continue?")) {
            elements.trigger('reinitialize.areYouSure');
            return true;
        }
        else {
            return false;
        }
    }
    return true;
}

function alterShowDeletedStatus(newStatus) {
    if(!checkAreYouSureForm()) return;
    document.cookie = "show_deleted=" + newStatus + "; path=/;";
    location.reload();
}

function alterShowMergeThreadStatus(newStatus, course) {
    if(!checkAreYouSureForm()) return;
    document.cookie = course + "_show_merged_thread=" + newStatus + "; path=/;";
    location.reload();
}

function modifyThreadList(currentThreadId, currentCategoriesId, course, loadFirstPage, success_callback){

    var categories_value = readCategoryValues();
    var thread_status_value = readThreadStatusValues();

    var unread_select_value = $("#unread").is(':checked');
    categories_value = (categories_value == null)?"":categories_value.join("|");
    thread_status_value = (thread_status_value == null)?"":thread_status_value.join("|");
    document.cookie = course + "_forum_categories=" + categories_value + ";";
    document.cookie = "forum_thread_status=" + thread_status_value + ";";
    document.cookie = "unread_select_value=" + unread_select_value + ";";
    var url = buildCourseUrl(['forum', 'threads']) + `?page_number=${(loadFirstPage?'1':'-1')}`;
    $.ajax({
        url: url,
        type: "POST",
        data: {
            thread_categories: categories_value,
            thread_status: thread_status_value,
            unread_select: unread_select_value,
            currentThreadId: currentThreadId,
            currentCategoriesId: currentCategoriesId,
            csrf_token: csrfToken
        },
        success: function(r){
            var x = JSON.parse(r)['data'];
            var page_number = parseInt(x.page_number);
            var threadCount = parseInt(x.count);
            x = x.html;
            x = `${x}`;
            var jElement = $("#thread_list");
            jElement.children(":not(.fas)").remove();
            $("#thread_list .fa-caret-up").after(x);
            jElement.data("prev_page", page_number - 1);
            jElement.data("next_page", page_number + 1);
            jElement.data("dynamic_lock_load", false);
            $("#thread_list .fa-spinner").hide();
            if(loadFirstPage) {
                $("#thread_list .fa-caret-up").hide();
                $("#thread_list .fa-caret-down").show();
            }
            else {
                $("#thread_list .fa-caret-up").show();
                $("#thread_list .fa-caret-down").hide();
            }

            $('#num_filtered').text(threadCount);

            dynamicScrollLoadIfScrollVisible(jElement);
            loadThreadHandler();
            if(success_callback != null) {
                success_callback();
            }
        },
        error: function(){
            window.alert("Something went wrong when trying to filter. Please try again.");
            document.cookie = course + "_forum_categories=;";
            document.cookie = "forum_thread_status=;";
        }
    })
}

function replyPost(post_id){
    if ( $('#'+ post_id + '-reply').css('display') == 'block' ){
        $('#'+ post_id + '-reply').css("display","none");
    }
    else {
        hideReplies();
        $('#'+ post_id + '-reply').css('display', 'block');
    }
}

function generateCodeMirrorBlocks(container_element) {
    var codeSegments = container_element.querySelectorAll(".code");
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
            //Default height for CodeMirror is 300px... 500px looks good
            var h = (editor0.defaultTextHeight()) * lineCount + 15;
            editor0.setSize("100%", (h > 500 ? 500 : h) + "px");
        }

        editor0.setOption("theme", "eclipse");
        editor0.refresh();

    }
}

function showSplit(post_id) {
  //If a thread was merged in the database earlier, we want to reuse the thread id and information
  //so we don't have any loose ends
  var url = buildCourseUrl(['forum', 'posts', 'splitinfo']);
  $.ajax({
    url: url,
    type: "POST",
    data: {
      post_id: post_id,
      csrf_token: csrfToken
    },
    success: function(data) {
      try {
        var json = JSON.parse(data);
      } catch (err) {
        var message = '<div class="inner-message alert alert-error" style="position: fixed;top: 40px;left: 50%;width: 40%;margin-left: -20%;" id="theid"><a class="fas fa-times message-close" onClick="removeMessagePopup(\'theid\');"></a><i class="fas fa-times-circle"></i>Error parsing data. Please try again.</div>';
        $('#messages').append(message);
        return;
      }
      if(json['status'] === 'fail'){
        var message ='<div class="inner-message alert alert-error" style="position: fixed;top: 40px;left: 50%;width: 40%;margin-left: -20%;" id="theid"><a class="fas fa-times message-close" onClick="removeMessagePopup(\'theid\');"></a><i class="fas fa-times-circle"></i>' + json['message'] + '</div>';
        $('#messages').append(message);
        return;
      }
      json = json['data'];
      if(json['merged_thread_id'] === -1) {
        document.getElementById("split_post_previously_merged").style.display = "none";
        document.getElementById("split_post_submit").disabled = true;
      } else {
        document.getElementById("split_post_previously_merged").style.display = "block";
        document.getElementById("split_post_submit").disabled = false;
        captureTabInModal('popup-post-split', false);
      }
      document.getElementById("split_post_input").value = json['title'];
      document.getElementById("split_post_id").value = post_id;
      var i;
      for(i = 0; i < json['all_categories_list'].length; i++) {
        var id = json["all_categories_list"][i]["category_id"];
        var target = "#split_post_category_" + id;
        if(json["categories_list"].includes(id)) {
          if(!($(target).hasClass("btn-selected"))) {
            $(target).addClass("btn-selected").trigger("eventChangeCatClass");
            $(target).find("input[type='checkbox']").prop("checked", true);
          }
        } else {
          if($(target).hasClass("btn-selected")) {
            $(target).removeClass("btn-selected").trigger("eventChangeCatClass");
            $(target).find("input[type='checkbox']").prop("checked", false);
          }
        }
      }
      $("#popup-post-split").show();
      captureTabInModal("popup-post-split");
    },
    error: function(){
      window.alert("Something went wrong while trying to get post information for splitting. Try again later.");
    }
  });
}

function showHistory(post_id) {
    var url = buildCourseUrl(['forum', 'posts', 'history']);
    $.ajax({
        url: url,
        type: "POST",
        data: {
            post_id: post_id,
            csrf_token: csrfToken
        },
        success: function(data){
            try {
                var json = JSON.parse(data);
            } catch (err){
                var message ='<div class="inner-message alert alert-error" style="position: fixed;top: 40px;left: 50%;width: 40%;margin-left: -20%;" id="theid"><a class="fas fa-times message-close" onClick="removeMessagePopup(\'theid\');"></a><i class="fas fa-times-circle"></i>Error parsing data. Please try again.</div>';
                $('#messages').append(message);
                return;
            }
            if(json['status'] === 'fail'){
                var message ='<div class="inner-message alert alert-error" style="position: fixed;top: 40px;left: 50%;width: 40%;margin-left: -20%;" id="theid"><a class="fas fa-times message-close" onClick="removeMessagePopup(\'theid\');"></a><i class="fas fa-times-circle"></i>' + json['message'] + '</div>';
                $('#messages').append(message);
                return;
            }
            $("#popup-post-history").show();
            captureTabInModal("popup-post-history");
            $("#popup-post-history .post_box.history_box").remove();
            $("#popup-post-history .form-body").css("padding", "5px");
            var dummy_box = $($("#popup-post-history .post_box")[0]);
            json = json['data'];
            for(var i = json.length - 1 ; i >= 0 ; i -= 1) {
                var post = json[i];
                box = dummy_box.clone();
                box.show();
                box.addClass("history_box");
                box.find(".post_content").html(post['content']);
                if(post.is_staff_post) {
                    box.addClass("important");
                }

                var first_name = post['user_info']['first_name'].trim();
                var last_name = post['user_info']['last_name'].trim();
                var author_user_id = post['user'];
                var visible_username = first_name + " " + ((last_name.length == 0) ? '' : (last_name.substr(0 , 1) + "."));
                var info_name = first_name + " " + last_name + " (" + author_user_id + ")";
                var visible_user_json = JSON.stringify(visible_username);
                info_name = JSON.stringify(info_name);
                var user_button_code = "<a style='margin-right:2px;display:inline-block; color:black;' onClick='changeName(this.parentNode, " + info_name + ", " + visible_user_json + ", false)' title='Show full user information'><i class='fas fa-eye' aria-hidden='true'></i></a>&nbsp;";
                if(!author_user_id){
                  user_button_code = ""
                }
                box.find("span.edit_author").html("<strong>"+visible_username+"</strong> "+post['post_time']);
                box.find("span.edit_author").before(user_button_code);
                $("#popup-post-history .form-body").prepend(box);
            }
            generateCodeMirrorBlocks($("#popup-post-history")[0]);
        },
        error: function(){
            window.alert("Something went wrong while trying to display post history. Please try again.");
        }
    });
}

function addNewCategory(csrf_token){
    var newCategory = $("#new_category_text").val();
    var url = buildCourseUrl(['forum', 'categories', 'new']);
    $.ajax({
        url: url,
        type: "POST",
        data: {
            newCategory: newCategory,
            csrf_token: csrf_token
        },
        success: function(data){
            try {
                var json = JSON.parse(data);
            } catch (err){
                var message ='<div class="inner-message alert alert-error" style="position: fixed;top: 40px;left: 50%;width: 40%;margin-left: -20%;" id="theid"><a class="fas fa-times message-close" onClick="removeMessagePopup(\'theid\');"></a><i class="fas fa-times-circle"></i>Error parsing data. Please try again.</div>';
                $('#messages').append(message);
                return;
            }
            if(json['status'] === 'fail'){
                var message ='<div class="inner-message alert alert-error" style="position: fixed;top: 40px;left: 50%;width: 40%;margin-left: -20%;" id="theid"><a class="fas fa-times message-close" onClick="removeMessagePopup(\'theid\');"></a><i class="fas fa-times-circle"></i>' + json['message'] + '</div>';
                $('#messages').append(message);
                return;
            }
            var message ='<div class="inner-message alert alert-success" style="position: fixed;top: 40px;left: 50%;width: 40%;margin-left: -20%;" id="theid"><a class="fas fa-times message-close" onClick="removeMessagePopup(\'theid\');"></a><i class="fas fa-check-circle"></i>Successfully created category "'+ escapeSpecialChars(newCategory) +'".</div>';
            $('#messages').append(message);
            $('#new_category_text').val("");
            // Create new item in #ui-category-list using dummy category
            var category_id = json['data']['new_id'];
            var category_color_code = "#000080";
            var category_desc = escapeSpecialChars(newCategory);
            newelement = $($('#ui-category-template li')[0]).clone(true);
            newelement.attr('id',"categorylistitem-"+category_id);
            newelement.css('color',category_color_code);
            newelement.find(".categorylistitem-desc span").text(category_desc);
            newelement.find(".category-color-picker").val(category_color_code);
            newelement.show();
            newelement.addClass("category-sortable");
            newcatcolorpicker = newelement.find(".category-color-picker");
            newcatcolorpicker.css("background-color",newcatcolorpicker.val());
            $('#ui-category-list').append(newelement);
            $(".category-list-no-element").hide();
            refreshCategories();
        },
        error: function(){
            window.alert("Something went wrong while trying to add a new category. Please try again.");
        }
    })
}

function deleteCategory(category_id, category_desc, csrf_token){
    var url = buildCourseUrl(['forum', 'categories', 'delete']);
    $.ajax({
        url: url,
        type: "POST",
        data: {
            deleteCategory: category_id,
            csrf_token: csrf_token
        },
        success: function(data){
            try {
                var json = JSON.parse(data);
            } catch (err){
                var message ='<div class="inner-message alert alert-error" style="position: fixed;top: 40px;left: 50%;width: 40%;margin-left: -20%;" id="theid"><a class="fas fa-times message-close" onClick="removeMessagePopup(\'theid\');"></a><i class="fas fa-times-circle"></i>Error parsing data. Please try again.</div>';
                $('#messages').append(message);
                return;
            }
            if(json['status'] === 'fail'){
                var message ='<div class="inner-message alert alert-error" style="position: fixed;top: 40px;left: 50%;width: 40%;margin-left: -20%;" id="theid"><a class="fas fa-times message-close" onClick="removeMessagePopup(\'theid\');"></a><i class="fas fa-times-circle"></i>' + json['message'] + '</div>';
                $('#messages').append(message);
                return;
            }
            var message ='<div class="inner-message alert alert-success" style="position: fixed;top: 40px;left: 50%;width: 40%;margin-left: -20%;" id="theid"><a class="fas fa-times message-close" onClick="removeMessagePopup(\'theid\');"></a><i class="fas fa-check-circle"></i>Successfully deleted category "'+ escapeSpecialChars(category_desc) +'"</div>';
            $('#messages').append(message);
            $('#categorylistitem-'+category_id).remove();
            refreshCategories();
        },
        error: function(){
            window.alert("Something went wrong while trying to add a new category. Please try again.");
        }
    })
}

function editCategory(category_id, category_desc, category_color, csrf_token) {
    if(category_desc === null && category_color === null) {
        return;
    }
    var data = {category_id: category_id, csrf_token: csrf_token};
    if(category_desc !== null) {
        data['category_desc'] = category_desc;
    }
    if(category_color !== null) {
        data['category_color'] = category_color;
    }
    var url = buildCourseUrl(['forum', 'categories', 'edit']);
    $.ajax({
        url: url,
        type: "POST",
        data: data,
        success: function(data){
            try {
                var json = JSON.parse(data);
            } catch (err){
                var message ='<div class="inner-message alert alert-error" style="position: fixed;top: 40px;left: 50%;width: 40%;margin-left: -20%;" id="theid"><a class="fas fa-times message-close" onClick="removeMessagePopup(\'theid\');"></a><i class="fas fa-times-circle"></i>Error parsing data. Please try again.</div>';
                $('#messages').append(message);
                return;
            }
            if(json['status'] === 'fail'){
                var message ='<div class="inner-message alert alert-error" style="position: fixed;top: 40px;left: 50%;width: 40%;margin-left: -20%;" id="theid"><a class="fas fa-times message-close" onClick="removeMessagePopup(\'theid\');"></a><i class="fas fa-times-circle"></i>' + json['message'] + '</div>';
                $('#messages').append(message);
                return;
            }
            var message ='<div class="inner-message alert alert-success" style="position: fixed;top: 40px;left: 50%;width: 40%;margin-left: -20%;" id="theid"><a class="fas fa-times message-close" onClick="removeMessagePopup(\'theid\');"></a><i class="fas fa-check-circle"></i>Successfully updated!</div>';
            $('#messages').append(message);
            setTimeout(function() {removeMessagePopup('theid');}, 1000);
            if(category_color !== null) {
                $("#categorylistitem-"+category_id).css("color",category_color);
            }
            if(category_desc !== null) {
                $("#categorylistitem-"+category_id).find(".categorylistitem-desc span").text(category_desc);
            }
            refreshCategories();
        },
        error: function(){
            window.alert("Something went wrong while trying to add a new category. Please try again.");
        }
    });
}

function refreshCategories() {
    if($('#ui-category-list').length) {
        // Refresh cat-buttons from #ui-category-list

        var data = $('#ui-category-list').sortable('serialize');
        if(!data.trim()) {
            return;
        }
        data = data.split("&");
        var order = [];
        for(var i = 0; i<data.length; i+=1) {
            var category_id = parseInt(data[i].split('=')[1]);
            var category_desc = $("#categorylistitem-"+category_id+" .categorylistitem-desc span").text().trim();
            var category_color = $("#categorylistitem-"+category_id+" select").val();
            order.push([category_id, category_desc, category_color]);
        }

        // Obtain current selected category
        var selected_button = new Set();
        var category_pick_buttons = $('.cat-buttons');
        for(var i = 0; i<category_pick_buttons.length; i+=1) {
            var cat_button_checkbox = $(category_pick_buttons[i]).find("input");
            var category_id = parseInt(cat_button_checkbox.val());
            if(cat_button_checkbox.prop("checked")) {
                selected_button.add(category_id);
            }
        }

        // Refresh selected categories
        $('#categories-pick-list').empty();
        order.forEach(function(category) {
            var category_id = category[0];
            var category_desc = category[1];
            var category_color = category[2];
            var selection_class = "";
            if(selected_button.has(category_id)) {
                selection_class = "btn-selected";
            }
            var element = ' <div tabindex="0" class="btn cat-buttons '+selection_class+'" data-color="'+category_color+'">'+category_desc+'\
                                <input aria-label="Category: '+category_desc+'" type="checkbox" name="cat[]" value="'+category_id+'">\
                            </div>';
            $('#categories-pick-list').append(element);
        });

        $(".cat-buttons input[type='checkbox']").each(function() {
            if($(this).parent().hasClass("btn-selected")) {
                $(this).prop("checked",true);
            }
        });
    }

    // Selectors for categories pick up
    // If JS enabled hide checkbox
    $("div.cat-buttons input").hide();

    $(".cat-buttons").click(function() {
        if($(this).hasClass("btn-selected")) {
            $(this).removeClass("btn-selected");
            $(this).find("input[type='checkbox']").prop("checked", false);
        }
        else {
            $(this).addClass("btn-selected");
            $(this).find("input[type='checkbox']").prop("checked", true);
        }
        $(this).trigger("eventChangeCatClass");
    });

    $(".cat-buttons").bind("eventChangeCatClass", changeColorClass);
    $(".cat-buttons").trigger("eventChangeCatClass");
}

function changeColorClass(){
  var color = $(this).data('color');
  $(this).css("border-color",color);
  if($(this).hasClass("btn-selected")) {
    $(this).css("background-color",color);
    $(this).css("color","white");
  }
    else {
    $(this).css("background-color","white");
    $(this).css("color", color);
  }
}

function reorderCategories(csrf_token) {
    var data = $('#ui-category-list').sortable('serialize');
    data += "&csrf_token=" + csrf_token;
    var url = buildCourseUrl(['forum', 'categories', 'reorder']);
    $.ajax({
        url: url,
        type: "POST",
        data: data,
        success: function(data){
            try {
                var json = JSON.parse(data);
            } catch (err){
                var message ='<div class="inner-message alert alert-error" style="position: fixed;top: 40px;left: 50%;width: 40%;margin-left: -20%;" id="theid"><a class="fas fa-times message-close" onClick="removeMessagePopup(\'theid\');"></a><i class="fas fa-times-circle"></i>Error parsing data. Please try again.</div>';
                $('#messages').append(message);
                return;
            }
            if(json['status'] === 'fail'){
                var message ='<div class="inner-message alert alert-error" style="position: fixed;top: 40px;left: 50%;width: 40%;margin-left: -20%;" id="theid"><a class="fas fa-times message-close" onClick="removeMessagePopup(\'theid\');"></a><i class="fas fa-times-circle"></i>' + json['message'] + '</div>';
                $('#messages').append(message);
                return;
            }
            var message ='<div class="inner-message alert alert-success" style="position: fixed;top: 40px;left: 50%;width: 40%;margin-left: -20%;" id="theid"><a class="fas fa-times message-close" onClick="removeMessagePopup(\'theid\');"></a><i class="fas fa-check-circle"></i>Successfully reordered categories.';
            $('#messages').append(message);
            setTimeout(function() {removeMessagePopup('theid');}, 1000);
            refreshCategories();
        },
        error: function(){
            window.alert("Something went wrong while trying to reordering categories. Please try again.");
        }
    });
}

/*This function ensures that only one reply box is open at a time*/
function hideReplies(){
    var hide_replies = document.getElementsByClassName("reply-box");
    for(var i = 0; i < hide_replies.length; i++){
        hide_replies[i].style.display = "none";
    }
}

function deletePostToggle(isDeletion, thread_id, post_id, author, time, csrf_token){
    if(!checkAreYouSureForm()) return;
    var type = (isDeletion ? '0' : '2');
    var message = (isDeletion?"delete":"undelete");

    var confirm = window.confirm("Are you sure you would like to " + message + " this post?: \n\nWritten by:  " + author + "  @  " + time + "\n\nPlease note: The replies to this comment will also be " + message + "d. \n\nIf you are " + message + " the first post in a thread this will " + message + " the entire thread.");
    if(confirm){
        var url = buildCourseUrl(['forum', 'posts', 'modify']) + `?modify_type=${type}`;
        $.ajax({
            url: url,
            type: "POST",
            data: {
                post_id: post_id,
                thread_id: thread_id,
                csrf_token: csrf_token
            },
            success: function(data){
                try {
                    var json = JSON.parse(data);
                } catch (err){
                    var message ='<div class="inner-message alert alert-error" style="position: fixed;top: 40px;left: 50%;width: 40%;margin-left: -20%;" id="theid"><a class="fas fa-times message-close" onClick="removeMessagePopup(\'theid\');"></a><i class="fas fa-times-circle"></i>Error parsing data. Please try again.</div>';
                    $('#messages').append(message);
                    return;
                }
                if(json['status'] === 'fail'){
                    var message ='<div class="inner-message alert alert-error" style="position: fixed;top: 40px;left: 50%;width: 40%;margin-left: -20%;" id="theid"><a class="fas fa-times message-close" onClick="removeMessagePopup(\'theid\');"></a><i class="fas fa-times-circle"></i>' + json['message'] + '</div>';
                    $('#messages').append(message);
                    return;
                }
                var new_url = "";
                switch(json['data']['type']){
                    case "thread":
                      var course = document.body.dataset.courseUrl.split('/').pop();
                      window.socketClient.send({'course': course, 'type': "delete_thread", 'thread_id': thread_id});
                      new_url = buildCourseUrl(['forum', 'threads']);
                      break;
                    case "post":
                      new_url = buildCourseUrl(['forum', 'threads', thread_id]);
                      break;
                    default:
                        new_url = buildCourseUrl(['forum', 'threads']);
                        break;
                }
                window.location.replace(new_url);
            },
            error: function(){
                window.alert("Something went wrong while trying to delete/undelete a post. Please try again.");
            }
        })
    }
}

function alterAnnouncement(thread_id, confirmString, type, csrf_token){
    var confirm = window.confirm(confirmString);
    if(confirm){
        var url = buildCourseUrl(['forum', 'announcements']) + `?type=${type}`;
        $.ajax({
            url: url,
            type: "POST",
            data: {
                thread_id: thread_id,
                csrf_token: csrf_token

            },
            success: function(data){
              var course = document.body.dataset.courseUrl.split('/').pop();
              if (type)
                  window.socketClient.send({'course': course, 'type': "announce_thread", 'thread_id': thread_id});
                else window.socketClient.send({'course': course, 'type': "unpin_thread", 'thread_id': thread_id});
                window.location.reload();
            },
            error: function(){
                window.alert("Something went wrong while trying to remove announcement. Please try again.");
            }
        })
    }
}

function pinThread(thread_id, type){
    var url = buildCourseUrl(['forum', 'threads', 'pin']) + `?type=${type}`;
    $.ajax({
        url: url,
        type: "POST",
        data: {
            thread_id: thread_id,
            csrf_token: csrfToken
        },
        success: function(data){
            window.location.replace(buildCourseUrl(['forum', 'threads', thread_id]));
        },
        error: function(){
            window.alert("Something went wrong while trying on pin/unpin thread. Please try again.");
        }
    });
}


function addMarkdownCode(type, divTitle){
    var cursor = $(divTitle).prop('selectionStart');
    var text = $(divTitle).val();
    var insert = "";
    if(type == 1) {
        insert = "[display text](url)";
    }
    else if(type == 0){
        insert = "```" +
            "\ncode\n```";
    }
    else if(type == 2){
        insert = "__bold text__ ";
    }
    else if(type == 3){
        insert = "_italic text_ ";
    }
    $(divTitle).val(text.substring(0, cursor) + insert + text.substring(cursor));
}

function checkInputMaxLength(obj){
    if($(obj).val().length == $(obj).attr('maxLength')){
        alert('Maximum input length reached!');
        $(obj).val($(obj).val().substr(0, $(obj).val().length));
    }
}

function sortTable(sort_element_index, reverse=false){
    var table = document.getElementById("forum_stats_table");
    var switching = true;
    while(switching){
        switching=false;
        var rows = table.getElementsByTagName("TBODY");
        for(var i=1;i<rows.length-1;i++){

            var a = rows[i].getElementsByTagName("TR")[0].getElementsByTagName("TD")[sort_element_index];
            var b = rows[i+1].getElementsByTagName("TR")[0].getElementsByTagName("TD")[sort_element_index];
            if (reverse){
                if (sort_element_index == 0 ? a.innerHTML<b.innerHTML : parseInt(a.innerHTML) > parseInt(b.innerHTML)){
                    rows[i].parentNode.insertBefore(rows[i+1],rows[i]);
                    switching=true;
                }
            }
            else {
                if(sort_element_index == 0 ? a.innerHTML>b.innerHTML : parseInt(a.innerHTML) < parseInt(b.innerHTML)){
                    rows[i].parentNode.insertBefore(rows[i+1],rows[i]);
                    switching=true;
                }
            }
        }

    }

    var row0 = table.getElementsByTagName("TBODY")[0].getElementsByTagName("TR")[0];
    var headers = row0.getElementsByTagName("TD");

    for(var i = 0;i<headers.length;i++){
        var index = headers[i].innerHTML.indexOf(' ↓');
        var reverse_index = headers[i].innerHTML.indexOf(' ↑');

        if(index > -1 || reverse_index > -1){
            headers[i].innerHTML = headers[i].innerHTML.slice(0, -2);
        }
    }
    if (reverse) {
        headers[sort_element_index].innerHTML = headers[sort_element_index].innerHTML + ' ↑';
    }
    else {
        headers[sort_element_index].innerHTML = headers[sort_element_index].innerHTML + ' ↓';
    }
}

function loadThreadHandler(){
    $("a.thread_box_link").click(function(event){
        event.preventDefault();
        var obj = this;
        var thread_id = $(obj).data("thread_id");

        var url = buildCourseUrl(['forum', 'threads', thread_id]);
        $.ajax({
            url: url,
            type: "POST",
            data: {
                thread_id: thread_id,
                ajax: "true",
                csrf_token: csrfToken
            },
            success: function(data){
                try {
                    var json = JSON.parse(data);
                } catch (err){
                    var message ='<div class="inner-message alert alert-error" style="position: fixed;top: 40px;left: 50%;width: 40%;margin-left: -20%;" id="theid"><a class="fas fa-times message-close" onClick="removeMessagePopup(\'theid\');"></a><i class="fas fa-times-circle"></i>Error parsing data. Please try again.</div>';
                    $('#messages').append(message);
                    return;
                }
                if(json['status'] === 'fail'){
                    var message ='<div class="inner-message alert alert-error" style="position: fixed;top: 40px;left: 50%;width: 40%;margin-left: -20%;" id="theid"><a class="fas fa-times message-close" onClick="removeMessagePopup(\'theid\');"></a><i class="fas fa-times-circle"></i>' + json['message'] + '</div>';
                    $('#messages').append(message);
                    return;
                }
                if (typeof json.data.merged !== 'undefined') {
                  window.location.replace(json.data.destination);
                  return;
                }
                $(obj).find('.thread_box').removeClass('new_thread');

                $('.thread_box').removeClass('active');

                $(obj).children("div.thread_box").addClass('active');

                $('#posts_list').empty().html(JSON.parse(json.data.html));
                window.history.pushState({"pageTitle":document.title},"", url);

                enableTabsInTextArea('.post_content_reply');
                saveScrollLocationOnRefresh('posts_list');

                $(".post_reply_from").submit(publishPost);

            },
            error: function(){
                window.alert("Something went wrong while trying to display thread details. Please try again.");
            }
        });
    });
}

function loadAllInlineImages() {
  $(".attachment-btn").each(function () {
    $(this).click();
  });
  $(".attachment-well").each(function () {
    $(this).show();
  });
}

function loadInlineImages(encoded_data) {
  var data = JSON.parse(encoded_data);
  var attachment_well = $("#"+data[data.length-1]);

  if (attachment_well.is(':visible'))
    attachment_well.hide();
  else {
    attachment_well.show();
  }

  // if they're no images loaded for this well
  if (attachment_well.children().length === 0 ) {
    // add image tags
    for (var i = 0; i < data.length - 1; i++) {
      var attachment = data[i];
      var url = attachment[0];
      var img = $('<img src="' + url + '" alt="Click to view attachment in popup" title="Click to view attachment in popup" class="attachment-img">');
      var title = $('<p>' + escapeSpecialChars(decodeURI(attachment[2])) + '</p>')
      img.click(function() {
        var url = $(this).attr('src');
        window.open(url,"_blank","toolbar=no,scrollbars=yes,resizable=yes, width=700, height=600");
      });
      attachment_well.append(img);
      attachment_well.append(title);
    }
  }

}

var filters_applied = [];

// Taken from https://stackoverflow.com/a/1988361/2650341

if (!Array.prototype.inArray) {
    Object.defineProperty(Array.prototype, 'inArray', {
        value: function(comparer) {
            for (let i=0; i < this.length; i++) {
                if (comparer(this[i])) {
                    return i;
                }
            }
            return false;
        }
    });
}

// adds an element to the array if it does not already exist using a comparer
// function
if (!Array.prototype.toggleElement) {
    Object.defineProperty(Array.prototype, 'toggleElement', {
        value: function(element, comparer) {
            var index = this.inArray(comparer);
            if ((typeof(index) == "boolean" && !index) || (typeof(index) == "int" && index === 0)) {
                this.push(element);
            }
            else {
                this.splice(index, 1);
            }
        }
    });
}

function clearForumFilter(){
    if(checkUnread()){
        $('#filter_unread_btn').click();
    }
    window.filters_applied = [];
    $('#thread_category button, #thread_status_select button').data('btn-selected', "false").removeClass('filter-active').addClass('filter-inactive');
    $('#filter_unread_btn').removeClass('filter-active').addClass('filter-inactive');
    $('#clear_filter_button').hide();

    updateThreads(true, null);
    return false;
}

function loadFilterHandlers(){

    $('#filter_unread_btn').mousedown(function (e) {
        $(this).toggleClass('filter-inactive filter-active');
    });

    $('#thread_category button, #thread_status_select button').mousedown(function(e) {
        e.preventDefault();
        var current_selection = $(this).data('btn-selected');

        if(current_selection==="true"){
            $(this).data('btn-selected', "false").removeClass('filter-active').addClass('filter-inactive');
        }
        else{
            $(this).data('btn-selected', "true").removeClass('filter-inactive').addClass('filter-active');
        }

        var filter_text = $(this).text();

        window.filters_applied.toggleElement(filter_text, function(e) {
            return e === filter_text;
        });

        if(window.filters_applied.length == 0){
            clearForumFilter();
        }
        else {
            $('#clear_filter_button').css('display', 'inline-block');
        }
        updateThreads(true, null);
        return true;
    });

    $('#unread').change(function(e) {
        e.preventDefault();
        updateThreads(true,null);
        checkUnread();
        return true;
    });
}

function thread_post_handler(){
    $('.submit_unresolve').click(function(event){
        var post_box_id = $(this).data("post_box_id");
        $('#thread_status_input_'+post_box_id).val(-1);
        return true;
    });
}

function forumFilterBar(){
    $('#forum_filter_bar').toggle();
}

function checkUnread(){
    if($('#unread').prop("checked")){
        unread_marked = true;
        $('#filter_unread_btn').removeClass('filter-inactive').addClass('filter-active');
        $('#clear_filter_button').css('display', 'inline-block');
        return true;
    }
    else{
        return false;
    }
}

// Used to update thread content in the "Merge Thread"
// modal.

function updateSelectedThreadContent(selected_thread_first_post_id){
    var url = buildCourseUrl(['forum', 'posts', 'get']);
    $.ajax({
        url : url,
        type : "POST",
        data : {
            post_id : selected_thread_first_post_id,
            csrf_token: csrfToken
        },
        success: function(data) {
            try {
                var json = JSON.parse(data);
            } catch(err) {
                var message ='<div class="inner-message alert alert-error" style="position: fixed;top: 40px;left: 50%;width: 40%;margin-left: -20%;" id="theid"><a class="fas fa-times message-close" onClick="removeMessagePopup(\'theid\');"></a><i class="fas fa-times-circle"></i>Error parsing data. Please try again. Error is ' + err +'</div>';
                $('#messages').append(message);
                return;
            }

            if(json['status'] === 'fail'){
                var message ='<div class="inner-message alert alert-error" style="position: fixed;top: 40px;left: 50%;width: 40%;margin-left: -20%;" id="theid"><a class="fas fa-times message-close" onClick="removeMessagePopup(\'theid\');"></a><i class="fas fa-times-circle"></i>' + json['message'] + '</div>';
                $('#messages').append(message);
                return;
            }

            json = json['data'];
            $("#thread-content").html(json['post']);
            if (json.markdown === true) {
                $('#thread-content').addClass('markdown-active');
            }
            else {
                $('#thread-content').removeClass('markdown-active');
            }
        },
        error: function(){
            window.alert("Something went wrong while trying to fetch content. Please try again.");
        }
    });
}

//When the user uses tab navigation on the thread list, this function
//helps to make sure the current thread is always visible on the page
function scrollThreadListTo(element){
  $(element).get(0).scrollIntoView({behavior: "smooth", block: "center"});
}
