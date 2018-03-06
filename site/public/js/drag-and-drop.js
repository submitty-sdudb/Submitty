/*
References:
https://developer.mozilla.org/en-US/docs/Using_files_from_web_applications
https://developer.mozilla.org/en-US/docs/Web/API/FormData/Using_FormData_Objects
https://developer.mozilla.org/en-US/docs/Web/API/XMLHttpRequest/Using_XMLHttpRequest#Submitting_forms_and_uploading_files
https://developer.mozilla.org/en-US/docs/Web/Guide/HTML/Dragging_and_Dropping_Multiple_Items
https://www.sitepoint.com/html5-file-drag-and-drop/
https://www.sitepoint.com/html5-ajax-file-upload/
http://www.html5rocks.com/en/tutorials/file/dndfiles/
*/

// INITIALIZATION
//========================================================================================
var file_array = [];        // contains files uploaded for this submission
var previous_files = [];    // contains names of files selected from previous submission
var label_array = [];
var use_previous = false;
var changed = false;        // if files from previous submission changed

var empty_textboxes = true;

var student_ids = [];           // all student ids
var student_without_ids = [];   // student ids for those w/o submissions

// initializing file_array and prevous_files
function createArray(num_parts){
    if(file_array.length == 0){
        for(var i=0; i<num_parts; i++){
            file_array.push([]);
            previous_files.push([]);
            label_array.push([]);
        }
    }
}

// read in name of previously submitted file
function readPrevious(filename, part){
    changed = false;
    previous_files[part-1].push(filename);
}

function setUsePrevious() {
    use_previous = true;
}

// DRAG AND DROP EFFECT
//========================================================================================
// open a file browser if clicked on drop zone
function clicked_on_box(e){
  document.getElementById("input_file" + get_part_number(e)).click();
  e.stopPropagation();
}

// hover effect
function draghandle(e){
    e.preventDefault();
    e.stopPropagation();
    document.getElementById("upload" + get_part_number(e)).style.opacity = (e.type == "dragenter" || e.type == "dragover") ? .5 : "";
}

// ADD FILES FOR NEW SUBMISSION
//========================================================================================
// add files dragged
function drop(e){
    draghandle(e);
    var filestream= e.dataTransfer.files;
    var part = get_part_number(e);
    for(var i=0; i<filestream.length; i++){
        addFileWithCheck(filestream[i], part); // check for folders
    }
}

function get_part_number(e){
    if(e.target.id.substring(0, 6) == "upload"){
        return e.target.id.substring(6);
    }
    else{
        return e.target.parentNode.id.substring(6);
    }
}

// copy files selected from the file browser
function addFilesFromInput(part){
    var filestream = document.getElementById("input_file" + part).files;
    for(var i=0; i<filestream.length; i++){
        addFile(filestream[i], part); // folders will not be selected in file browser, no need for check
    }
    $('#input_file' + part).val("");
}

// Check for duplicate file names. This function returns an array.
// First element:
// 1 - a file with the same name found in previous submission
// 0 - a file with the same name already selected for this version
// -1 - does not exist files with the same name
// Second element: index of the file with the same name (if found)
function fileExists(file, part){
    for(var i = 0; i < previous_files[part-1].length; i++){
        if(previous_files[part-1][i] == file.name){
            return [1, i];
        }
    }

    for(var j = 0; j < file_array[part-1].length; j++){
        if(file_array[part-1][j].name == file.name){
            return [0, j];
        }
    }
    return [-1];
}

// add file with folder check
function addFileWithCheck(file, part){
    // try to open file if it looks suspicious:
    // no type, or with size of a typical folder size
    if(!file.type || file.size%4096 == 0){
        var reader = new FileReader();
        reader.onload = notFolder(file, part);
        reader.onerror = isFolder(file);
        reader.readAsBinaryString(file);
    }
    else{
        addFile(file, part);
    }
}

// add file if is not a folder
function notFolder(file, part){
    return function(e){ addFile(file, part); }
}

function isFolder(file){
    return function(e){ alert("Upload failed: " + file.name + " might be a folder."); }
}

function addFile(file, part){
    var i = fileExists(file, part);
    if( i[0] == -1 ){    // file does not exist
        // empty bucket if file is a zip and bucket is not empty
        if(file.name.substring(file.name.length - 4, file.name.length) == ".zip" && file_array[part-1].length + previous_files[part-1].length > 0 ){
            if(confirm("Note: All files currently in the bucket will be deleted if you try to upload a zip: " + file.name + ". Do you want to continue?")){
                deleteFiles(part);
            }
        }
        file_array[part-1].push(file);
        addLabel(file.name, (file.size/1024).toFixed(2), part, false);
    }
    else if(i[0] == 0){    // file already selected
        if(confirm("Note: " + file_array[part-1][i[1]].name + " is already selected. Do you want to replace it?")){
            file_array[part-1].splice(i[1], 1, file);
            removeLabel(file.name, part);
            addLabel(file.name, (file.size/1024).toFixed(2), part, false);
        }
    }
    else{    // file in previous submission
        if(confirm("Note: " + previous_files[part-1][i[1]] + " was in your previous submission. Do you want to replace it?")){
            file_array[part-1].push(file);
            previous_files[part-1].splice(i[1], 1);
            removeLabel(file.name, part);
            addLabel(file.name, (file.size/1024).toFixed(2), part, false);
            changed = true;
        }
    }

    setButtonStatus()
}

// REMOVE FILES
//========================================================================================
// delete files selected for a part
function deleteFiles(part) {
    if(file_array.length != 0){
        file_array[part-1] = [];
    }
    if(previous_files.length != 0){
        previous_files[part-1] = [];
    }
    var dropzone = document.getElementById("upload" + part);
    var labels = dropzone.getElementsByClassName("mylabel");
    while(labels[0]){
        dropzone.removeChild(labels[0]);
    }
    label_array[part-1] = [];
    changed = true;
    setButtonStatus();
}

function deleteSingleFile(filename, part, previous) {
    // Remove files from previous submission
    if (previous) {
        for (var i = 0; i < previous_files[part-1].length; i++){
            if(previous_files[part-1][i] == filename){
                previous_files[part-1].splice(i, 1);
                label_array[part-1].splice(i, 1);
                changed = true;
                break;
            }
        }
    }
    // Remove files uploaded for submission
    else{
        for (var j = 0; j < file_array[part-1].length; j++){
            if (file_array[part-1][j].name == filename) {
                file_array[part-1].splice(j, 1);
                label_array[part-1].splice(j, 1);
                break;
            }
        }
    }
    setButtonStatus();
}

function setButtonStatus() {

    // we only want to clear buckets if there's any labels in it (otherwise it's "blank")
    var labels = 0;
    for (var i = 0; i < label_array.length; i++) {
        labels += label_array[i].length;
    }

    if (labels == 0) {
        $("#startnew").prop("disabled", true);
        if (empty_textboxes) {
            $("#submit").prop("disabled", true);
        } else {
            $("#submit").prop("disabled", false);
        }
    }
    else {
        $("#startnew").prop("disabled", false);
        $("#submit").prop("disabled", false);
    }

    // We only have "non-previous" submissions if there's stuff in the file array as well as if we've
    // toggled the necessary flag that we're on a submission that would have previous (to prevent costly dom
    // lookups for the existance of #getprev id in the page)
    var files = 0;
    for (var j = 0; j < file_array.length; j++) {
        files += file_array[j].length;
    }

    if (use_previous && !changed && files == 0) {
        $("#getprev").prop("disabled", true);
    }
    else if (use_previous) {
        $("#getprev").prop("disabled", false);
    }
}

// LABELS FOR SELECTED FILES
//========================================================================================
function removeLabel(filename, part){
    var dropzone = document.getElementById("upload" + part);
    var labels = dropzone.getElementsByClassName("mylabel");
    for(var i = 0 ; i < labels.length; i++){
        if(labels[i].innerHTML.substring(0, filename.length) == filename){
            dropzone.removeChild(labels[i]);
            label_array[part-1].splice(i, 1);
            break;
        }
    }
}

function addLabel(filename, filesize, part, previous){
    // create element
    var tmp = document.createElement('label');
    tmp.setAttribute("class", "mylabel");
    tmp.innerHTML =  filename + " " + filesize + "kb <i class='fa fa-trash-o'></i><br />";
    // styling
    tmp.children[0].onmouseover = function(e){
        e.stopPropagation();
        this.style.color = "#FF3933";
    };
    tmp.children[0].onmouseout = function(e){
        e.stopPropagation();
        this.style.color = "black";
    };
    // remove file and label on click
    tmp.children[0].onclick = function(e){
        e.stopPropagation();
        this.parentNode.parentNode.removeChild(this.parentNode);
        deleteSingleFile(filename, part, previous);
    };
    // add to parent div
    var dropzone = document.getElementById("upload" + part);
    // Uncomment if want buttons for emptying single bucket
    // var deletebutton = document.getElementById("delete" + part);
    dropzone.appendChild(tmp);
    // dropzone.insertBefore(tmp, deletebutton);
    label_array[part-1].push(filename);
}

function handle_textbox_keypress() {
    empty_textboxes = false;
    setButtonStatus();
}

function handle_textbox_keypress() {
    empty_textboxes = false;
    setButtonStatus();
}

// BULK UPLOAD
//========================================================================================
function openFile(url_full) {
    window.open(url_full,"_blank","toolbar=no,scrollbars=yes,resizable=yes, width=700, height=600");
}

// moving to next input for split item submissions
// referenced https://stackoverflow.com/questions/18150090/jquery-scroll-element-to-the-middle-of-the-screen-instead-of-to-the-top-with-a
function moveNextInput(count) {
    var next_count = count+1;
    var next_input = "#users_" + next_count + " :first";
    if ($(next_input).length) {
        $(next_input).focus();
        $(next_input).select(); 

        var inputOffset = $(next_input).offset().top;
        var inputHeight = $(next_input).height();
        var windowHeight = $(window).height();
        var offset;

        if (inputHeight < windowHeight) {
            offset = inputOffset - ((windowHeight / 2) - (inputHeight / 2));
        }
        else {
            offset = inputOffset;
        }
        var speed = 500;
        $('html, body').animate({scrollTop:offset}, speed); 
    }
}



// HANDLE SUBMISSION
//========================================================================================
function isValidSubmission(){
    // check if new files added
    for (var i=0; i < file_array.length; i++) {
        if(file_array[i].length != 0){
            return true;
        }
    }
    // check if files from previous submission changed
    if (changed) {
        // check if previous submission files are emptied
        for (var j = 0; j < previous_files.length; j++) {
            if (previous_files[j] != 0) {
                return true;
            }
        }
    }
    return false;
}

/**
 * @param csrf_token
 * @param gradeable_id
 * @param user_id
 * @param is_pdf
 * @param path
 * @param count
 * @param makeSubmission, a callback function
 */
function validateUserId(csrf_token, gradeable_id, user_id, is_pdf, path, count, repo_id, makeSubmission) {

    var formData = new FormData();
    formData.append('csrf_token', csrf_token);
    formData.append('user_id', user_id);

    var url = buildUrl({'component': 'student', 'page': 'submission', 'action': 'verify', 'gradeable_id': gradeable_id});

    $.ajax({
        url: url,
        data: formData,
        processData: false,
        contentType: false,
        type: 'POST',
        success: function(data) {
            try {
                data = JSON.parse(data);
                if (data['success']) {
                    var make_submission = true;
                    if(data['previous_submission']){
                        var make_submission = confirm("One or more users you are submitting for had a previous submission. Do you wish to continue?")
                    }
                    if(make_submission){
                        makeSubmission(user_id, data['highest_version'], is_pdf, path, count, repo_id);
                    }
                }
                else {
                    alert("ERROR! \n\n" + data['message']);
                }
            }
            catch (e) {
                alert("Error parsing response from server. Please copy the contents of your Javascript Console and " +
                    "send it to an administrator, as well as what you were doing and what files you were uploading.");
                console.log(data);
            }
        },
        error: function() {
            $("#submit").prop("disabled", false);
            alert("Something went wrong. Please try again.");
        }
    });
}

/**
* @param csrf_token
* @param gradeable_id
* @param user_id
* @param path
* @param count
*/
function submitSplitItem(csrf_token, gradeable_id, user_id, path, count) {

    url = buildUrl({'component': 'student', 'page': 'submission', 'action': 'upload_split', 'gradeable_id': gradeable_id});
    return_url = buildUrl({'component': 'student','gradeable_id': gradeable_id});

    var formData = new FormData();

    formData.append('csrf_token', csrf_token);
    formData.append('user_id', user_id);
    formData.append('path', path);

    $.ajax({
        url: url,
        data: formData,
        processData: false,
        contentType: false,
        type: 'POST',
        success: function(data) {
            try {
                data = JSON.parse(data);
                if (data['success']) {
                    $("#bulk_submit_" + count).prop("disabled", true);
                    $("#bulk_delete_" + count).prop("disabled", true);
                    $("#users_" + count + " :input").prop("disabled", true);
                    var message ='<div id="submit_' + count +  '" class="inner-message alert alert-success"><a class="fa fa-times message-close" onClick="removeMessagePopup(\'submit_' + count +'\');"></a><i class="fa fa-times-circle"></i>' + data['message'] + '</div>';
                    $('#messages').append(message);
                    setTimeout(function() {
                        $('#submit_' + count).fadeOut();
                    }, 5000);
                    var index = student_without_ids.indexOf(user_id);
                    if (index > -1) {
                        student_without_ids.splice(index, 1);
                    }
                    return;
                }
                else {
                    if (data['message'] == "You do not have access to that page.") {
                        window.location.href = return_url;
                    }
                    else {
                        alert("ERROR! Please contact administrator with following error:\n\n" + data['message']);
                    }
                }
            }
            catch (e) {
                console.log(e);
                alert("Error parsing response from server. Please copy the contents of your Javascript Console and " +
                    "send it to an administrator, as well as what you were doing and what files you were uploading.");
                console.log(data);
            }
        },
        error: function() {
            alert("ERROR! Please contact administrator that you could not upload files.");
        }
    });
}

/**
* @param csrf_token
* @param gradeable_id
* @param path
* @param count
*/
function deleteSplitItem(csrf_token, gradeable_id, path, count) {

    submit_url = buildUrl({'component': 'student', 'page': 'submission', 'action': 'delete_split', 'gradeable_id': gradeable_id});

    var formData = new FormData();

    formData.append('csrf_token', csrf_token);
    formData.append('path', path);

    $.ajax({
        url: submit_url,
        data: formData,
        processData: false,
        contentType: false,
        type: 'POST',
        success: function(data) {
            try {
                data = JSON.parse(data);
                if (data['success']) {
                    $("#bulk_submit_" + count).prop("disabled", true);
                    $("#bulk_delete_" + count).prop("disabled", true);
                    $("#bulk_user_id_" + count).val("");
                    $("#bulk_user_id_" + count).prop("disabled", true);
                    var message ='<div id="delete_' + count + '" class="inner-message alert alert-success"><a class="fa fa-times message-close" onClick="removeMessagePopup(\'delete_' + count + '\');"></a><i class="fa fa-times-circle"></i>' + data['message'] + '</div>';
                    $('#messages').append(message);
                    setTimeout(function() {
                        $('#delete_' + count).fadeOut();
                    }, 5000);
                    return;
                }
                else {
                    alert("ERROR! Please contact administrator with following error:\n\n" + data['message']);
                }
            }
            catch (e) {
                alert("Error parsing response from server. Please copy the contents of your Javascript Console and " +
                    "send it to an administrator, as well as what you were doing and what files you were deleting.");
                console.log(data);
            }
        },
        error: function() {
            alert("ERROR! Please contact administrator that you could not delete files.");
        }
    });
}

/**
 * @param gradeable_id
 * @param num_pages
 */
function handleBulk(gradeable_id, num_pages) {
    $("#submit").prop("disabled", true);

    var formData = new FormData();

    if(num_pages == "") {
        alert("You didn't enter the # of page(s)!");
        $("#submit").prop("disabled", false);
        return;
    }
    else if(num_pages < 1 || num_pages % 1 != 0) {
        alert(num_pages + " is not a valid # of page(s)!");
        $("#submit").prop("disabled", false);
        return;
    }

    formData.append('num_pages', num_pages);

    for (var i = 0; i < file_array.length; i++) {
        for (var j = 0; j < file_array[i].length; j++) {
            if (file_array[i][j].name.indexOf("'") != -1 ||
                file_array[i][j].name.indexOf("\"") != -1) {
                alert("ERROR! You may not use quotes in your filename: " + file_array[i][j].name);
                return;
            }
            else if (file_array[i][j].name.indexOf("\\") != -1 ||
                file_array[i][j].name.indexOf("/") != -1) {
                alert("ERROR! You may not use a slash in your filename: " + file_array[i][j].name);
                return;
            }
            else if (file_array[i][j].name.indexOf("<") != -1 ||
                file_array[i][j].name.indexOf(">") != -1) {
                alert("ERROR! You may not use angle brackets in your filename: " + file_array[i][j].name);
                return;
            }
            formData.append('files' + (i + 1) + '[]', file_array[i][j], file_array[i][j].name);
        }
    }

    var url = buildUrl({'component': 'student', 'page': 'submission', 'action': 'bulk', 'gradeable_id': gradeable_id});
    var return_url = buildUrl({'component': 'student', 'gradeable_id': gradeable_id});

    $.ajax({
        url: url,
        data: formData,
        processData: false,
        contentType: false,
        type: 'POST',
        success: function(data) {
            $("#submit").prop("disabled", false);
            try {
                data = JSON.parse(data);
                if (data['success']) {
                    window.location.href = return_url;
                }
                else {
                    if (data['message'] == "You do not have access to that page.") {
                        window.location.href = return_url;
                    }
                    else {
                        alert("ERROR! \n\n" + data['message']);
                    }
                }
            }
            catch (e) {
                alert("Error parsing response from server. Please copy the contents of your Javascript Console and " +
                    "send it to an administrator, as well as what you were doing and what files you were uploading.");
                console.log(data);
            }
        },
        error: function() {
            $("#submit").prop("disabled", false);
            alert("ERROR! Please contact administrator that you could not upload files.");
        }
    });
}

/**
 * @param days_late
 * @param late_days_allowed
 * @param versions_used
 * @param versions_allowed
 * @param csrf_token
 * @param vcs_checkout
 * @param num_textboxes
 * @param user_id
 * @param repo_id
 * @param student_page
 * @param num_components
 */
function handleSubmission(days_late, late_days_allowed, versions_used, versions_allowed, csrf_token, vcs_checkout, num_textboxes, gradeable_id, user_id, repo_id, student_page, num_components) {
    $("#submit").prop("disabled", true);

    submit_url = buildUrl({'component': 'student', 'page': 'submission', 'action': 'upload', 'gradeable_id': gradeable_id});
    return_url = buildUrl({'component': 'student','gradeable_id': gradeable_id});

    var message = "";
    // check versions used
    if(versions_used >= versions_allowed) {
        message = "You have already made " + versions_used + " submissions.  You are allowed " + versions_allowed + " submissions before a small point penalty will be applied. Are you sure you want to continue?";
        if (!confirm(message)) {
            return;
        }
    }
    // check due date
    if (days_late > 0 && days_late <= late_days_allowed) {
        message = "Your submission will be " + days_late + " day(s) late. Are you sure you want to use " +days_late + " late day(s)?";
        if (!confirm(message)) {
            return;
        }
    }
    else if (days_late > 0) {
        message = "Your submission will be " + days_late + " days late. You are not supposed to submit unless you have an excused absence. Are you sure you want to continue?";
        if (!confirm(message)) {
            return;
        }
    }

    var formData = new FormData();

    formData.append('csrf_token', csrf_token);
    formData.append('vcs_checkout', vcs_checkout);
    formData.append('user_id', user_id);
    formData.append('repo_id', repo_id);
    formData.append('student_page', student_page)

    if (!vcs_checkout) {
        // Check if new submission
        if (!isValidSubmission() && empty_textboxes) {
            alert("Not a new submission.");
            window.location.reload();
            return;
        }

        // Files selected
        for (var i = 0; i < file_array.length; i++) {
            for (var j = 0; j < file_array[i].length; j++) {
                if (file_array[i][j].name.indexOf("'") != -1 ||
                    file_array[i][j].name.indexOf("\"") != -1) {
                    alert("ERROR! You may not use quotes in your filename: " + file_array[i][j].name);
                    return;
                }
                else if (file_array[i][j].name.indexOf("\\") != -1 ||
                    file_array[i][j].name.indexOf("/") != -1) {
                    alert("ERROR! You may not use a slash in your filename: " + file_array[i][j].name);
                    return;
                }
                else if (file_array[i][j].name.indexOf("<") != -1 ||
                    file_array[i][j].name.indexOf(">") != -1) {
                    alert("ERROR! You may not use angle brackets in your filename: " + file_array[i][j].name);
                    return;
                }
            formData.append('files' + (i + 1) + '[]', file_array[i][j], file_array[i][j].name);            }
        }
        // Files from previous submission
        formData.append('previous_files', JSON.stringify(previous_files));
    }

    var textbox_answers = [];
    for (var i = 0; i < num_textboxes; i++) {
        textbox_answers[i] = $("#textbox_"+i).val();
    }
    formData.append('textbox_answers', JSON.stringify(textbox_answers));

    if (student_page) {
        var pages = [];
        for (var i = 0; i < num_components; i++) {
            pages[i] = $("#page_"+i).val();
            if (pages[i] == "") {
                alert("You cannot leave a page textbox empty.");
                $("#submit").prop("disabled", false);
                return;
            }
            if (parseInt(pages[i]) < 1) {
                alert("Page numbers cannot be less than 1.");
                $("#submit").prop("disabled", false);
                return;
            }
        }
        formData.append('pages', JSON.stringify(pages));
    }


    $.ajax({
        url: submit_url,
        data: formData,
        processData: false,
        contentType: false,
        type: 'POST',
        success: function(data) {
            $("#submit").prop("disabled", false);
            try {
                data = JSON.parse(data);
                if (data['success']) {
                    window.location.href = return_url;
                }
                else {
                    if (data['message'] == "You do not have access to that page.") {
                        window.location.href = return_url;
                    }
                    else {
                        alert("ERROR! Please contact administrator with following error:\n\n" + data['message']);
                    }
                }
            }
            catch (e) {
                alert("Error parsing response from server. Please copy the contents of your Javascript Console and " +
                    "send it to an administrator, as well as what you were doing and what files you were uploading.");
                console.log(data);
            }
        },
        error: function() {
            $("#submit").prop("disabled", false);
            alert("ERROR! Please contact administrator that you could not upload files.");
        }
    });
}
