var siteUrl = undefined;
var csrfToken = undefined;

window.addEventListener("load", function() {
  for (const elem in document.body.dataset) {
    window[elem] = document.body.dataset[elem];
  }
});

/**
 * Acts in a similar fashion to Core->buildUrl() function within the PHP code
 * so that we do not have to pass in fully built URL to JS functions, but rather
 * construct them there as it makes sense (which helps on cutting down on potential
 * duplication of effort where we can replicate JS functions across multiple pages).
 *
 * @param {object} parts - Object representing URL parts to append to the URL
 * @returns {string} - Built up URL to use
 */
function buildUrl(parts) {
    var constructed = "";
    for (var part in parts) {
        if (parts.hasOwnProperty(part)) {
            constructed += "&" + part + "=" + parts[part];
        }
    }
    return document.body.dataset.siteUrl + constructed;
}

function loadTestcaseOutput(div_name, gradeable_id, who_id, index){
    orig_div_name = div_name
    div_name = "#" + div_name;
    var isVisible = $( div_name ).is( " :visible" );

    if(isVisible){
        toggleDiv(orig_div_name);
        $(div_name).empty();
    }else{
        var url = buildUrl({'component': 'grading', 'page': 'electronic', 'action': 'load_student_file',
            'gradeable_id': gradeable_id, 'who_id' : who_id, 'index' : index});

        $.ajax({
            url: url,
            success: function(data) {
                $(div_name).empty();
                $(div_name).html(data);
                toggleDiv(orig_div_name); 
            },
            error: function(e) {
                alert("Could not load diff, please refresh the page and try again.");
            }
        })
    }
}




/**
 *
 */
function editUserForm(user_id) {
    var url = buildUrl({'component': 'admin', 'page': 'users', 'action': 'get_user_details', 'user_id': user_id});
    $.ajax({
        url: url,
        success: function(data) {
            var json = JSON.parse(data);
            var form = $("#edit-user-form");
            form.css("display", "block");
            $('[name="edit_user"]', form).val("true");
            var user = $('[name="user_id"]', form);
            user.val(json['user_id']);
            user.attr('readonly', 'readonly');
            if (!user.hasClass('readonly')) {
                user.addClass('readonly');
            }
            $('[name="user_firstname"]', form).val(json['user_firstname']);
            if (json['user_preferred_firstname'] === null) {
                json['user_preferred_firstname'] = "";
            }
            $('[name="user_preferred_firstname"]', form).val(json['user_preferred_firstname']);
            $('[name="user_lastname"]', form).val(json['user_lastname']);
            $('[name="user_email"]', form).val(json['user_email']);
            var registration_section;
            if (json['registration_section'] === null) {
                registration_section = "null";
            }
            else {
                registration_section = json['registration_section'].toString();
            }
            var rotating_section;
            if (json['rotating_section'] === null) {
                rotating_section = "null";
            }
            else {
                rotating_section = json['rotating_section'].toString();
            }
            $('[name="registered_section"] option[value="' + registration_section + '"]', form).prop('selected', true);
            $('[name="rotating_section"] option[value="' + rotating_section + '"]', form).prop('selected', true);
            $('[name="manual_registration"]', form).prop('checked', json['manual_registration']);
            $('[name="user_group"] option[value="' + json['user_group'] + '"]', form).prop('selected', true);
            $("[name='grading_registration_section[]']").prop('checked', false);
            if (json['grading_registration_sections'] !== null && json['grading_registration_sections'] !== undefined) {
                json['grading_registration_sections'].forEach(function(val) {
                    $('#grs_' + val).prop('checked', true);
                });
            }

        },
        error: function() {
            alert("Could not load user data, please refresh the page and try again.");
        }
    })
}

function newUserForm() {
    $('.popup-form').css('display', 'none');
    var form = $("#edit-user-form");
    form.css("display", "block");
    $('[name="edit_user"]', form).val("false");
    $('[name="user_id"]', form).removeClass('readonly').removeAttr('readonly').val("");
    $('[name="user_firstname"]', form).val("");
    $('[name="user_preferred_firstname"]', form).val("");
    $('[name="user_lastname"]', form).val("");
    $('[name="user_email"]', form).val("");
    $('[name="registered_section"] option[value="null"]', form).prop('selected', true);
    $('[name="rotating_section"] option[value="null"]', form).prop('selected', true);
    $('[name="manual_registration"]', form).prop('checked', true);
    $('[name="user_group"] option[value="4"]', form).prop('selected', true);
    $("[name='grading_registration_section[]']").prop('checked', false);
}

function newDownloadForm() {
    $('.popup-form').css('display', 'none');
    var form = $('#download-form');
    form.css('display', 'block');
    $("#download-form input:checkbox").each(function() {
        if ($(this).val() === 'NULL') {
            $(this).prop('checked', false);
        } else {
            $(this).prop('checked', true);
        }
    });
}

function newGraderListForm() {
    $('.popup-form').css('display', 'none');
    var form = $("#grader-list-form");
    form.css("display", "block");
    $('[name="upload"]', form).val(null);
}

function newClassListForm() {
    $('.popup-form').css('display', 'none');
    var form = $("#class-list-form");
    form.css("display", "block");
    $('[name="move_missing"]', form).prop('checked', false);
    $('[name="upload"]', form).val(null);
}

function newDeleteGradeableForm(form_action, gradeable_name) {
    $('.popup-form').css('display', 'none');
    var form = $("#delete-gradeable-form");
    $('[name="delete-gradeable-message"]', form).html('');
    $('[name="delete-gradeable-message"]', form).append('<b>'+gradeable_name+'</b>');
    $('[name="delete-confirmation"]', form).attr('action', form_action);
    form.css("display", "block");
}

function copyToClipboard(code) {
    var download_info = JSON.parse($('#download_info_json_id').val());
    var required_emails = [];
    
    $('#download-form input:checkbox').each(function() {
        if ($(this).is(':checked')) {
            var thisVal = $(this).val();

            if (thisVal === 'instructor') {
                for (var i = 0; i < download_info.length; ++i) {
                    if (download_info[i].group === 'Instructor') {
                        required_emails.push(download_info[i].email);
                    }
                }
            }
            else if (thisVal === 'full_access_grader') {
                for (var i = 0; i < download_info.length; ++i) {
                    if (download_info[i].group === 'Full Access Grader (Grad TA)') {
                        required_emails.push(download_info[i].email);
                    }
                }
            }
            else if (thisVal === 'limited_access_grader') {
                for (var i = 0; i < download_info.length; ++i) {
                    if (download_info[i].group === "Limited Access Grader (Mentor)") {
                        required_emails.push(download_info[i].email);
                    }
                }
            }
            else {
                for (var i = 0; i < download_info.length; ++i) {
                    if (code === 'user') {
                        if (download_info[i].reg_section === thisVal) {
                            required_emails.push(download_info[i].email);
                        }
                    }
                    else if (code === 'grader') {
                        if (download_info[i].reg_section === 'All') {
                            required_emails.push(download_info[i].email);
                        }

                        if ($.inArray(thisVal, download_info[i].reg_section.split(',')) !== -1) {
                            required_emails.push(download_info[i].email);
                        }
                    }
                }
            }
        }
    });

    required_emails = $.unique(required_emails);
    var temp_element = $("<textarea></textarea>").text(required_emails.join(','));
    $(document.body).append(temp_element);
    temp_element.select();
    document.execCommand('copy');
    temp_element.remove();
    setTimeout(function() {
        $('#copybuttonid').prop('value', 'Copied');
    }, 0);
    setTimeout(function() {
        $('#copybuttonid').prop('value', 'Copy Emails to Clipboard');
    }, 1000);
}

function downloadCSV(code) {
    var download_info = JSON.parse($('#download_info_json_id').val());
    var csv_data = 'First Name,Last Name,User ID,Email,Registration Section,Rotation Section,Group\n';
    var required_user_id = [];

    $('#download-form input:checkbox').each(function() {
        if ($(this).is(':checked')) {
            var thisVal = $(this).val();

            if (thisVal === 'instructor') {
                for (var i = 0; i < download_info.length; ++i) {
                    if ((download_info[i].group === 'Instructor') && ($.inArray(download_info[i].user_id,required_user_id) === -1)) {
                        csv_data += [download_info[i].first_name, download_info[i].last_name, download_info[i].user_id, download_info[i].email, '"'+download_info[i].reg_section+'"', download_info[i].rot_section, download_info[i].group].join(',') + '\n';
                        required_user_id.push(download_info[i].user_id);
                    }
                }
            }
            else if (thisVal === 'full_access_grader') {
                for (var i = 0; i < download_info.length; ++i) {
                    if ((download_info[i].group === 'Full Access Grader (Grad TA)') && ($.inArray(download_info[i].user_id,required_user_id) === -1)) {
                        csv_data += [download_info[i].first_name, download_info[i].last_name, download_info[i].user_id, download_info[i].email, '"'+download_info[i].reg_section+'"', download_info[i].rot_section, download_info[i].group].join(',') + '\n';
                        required_user_id.push(download_info[i].user_id);
                    }
                }
            }
            else if (thisVal === 'limited_access_grader') {
                for (var i = 0; i < download_info.length; ++i) {
                    if ((download_info[i].group === 'Limited Access Grader (Mentor)') && ($.inArray(download_info[i].user_id,required_user_id) === -1)) {
                        csv_data += [download_info[i].first_name, download_info[i].last_name, download_info[i].user_id, download_info[i].email, '"'+download_info[i].reg_section+'"', download_info[i].rot_section, download_info[i].group].join(',') + '\n';
                        required_user_id.push(download_info[i].user_id);
                    }
                }
            }
            else {
                for (var i = 0; i < download_info.length; ++i) {
                    if (code === 'user') {
                        if ((download_info[i].reg_section === thisVal) && ($.inArray(download_info[i].user_id,required_user_id) === -1)) {
                            csv_data += [download_info[i].first_name, download_info[i].last_name, download_info[i].user_id, download_info[i].email, '"'+download_info[i].reg_section+'"', download_info[i].rot_section, download_info[i].group].join(',') + '\n';
                            required_user_id.push(download_info[i].user_id);
                        }
                    }
                    else if (code === 'grader') {
                        if ((download_info[i].reg_section === 'All') && ($.inArray(download_info[i].user_id,required_user_id) === -1)) {
                            csv_data += [download_info[i].first_name, download_info[i].last_name, download_info[i].user_id, download_info[i].email, '"'+download_info[i].reg_section+'"', download_info[i].rot_section, download_info[i].group].join(',') + '\n';
                            required_user_id.push(download_info[i].user_id);
                        }
                        if (($.inArray(thisVal, download_info[i].reg_section.split(',')) !== -1) && ($.inArray(download_info[i].user_id, required_user_id) === -1)) {
                            csv_data += [download_info[i].first_name, download_info[i].last_name, download_info[i].user_id, download_info[i].email, '"'+download_info[i].reg_section+'"', download_info[i].rot_section, download_info[i].group].join(',') + '\n';
                            required_user_id.push(download_info[i].user_id);
                        }
                    }
                }
            }
        }
    });

    var temp_element = $('<a id="downloadlink"></a>');
    var address = "data:text/csv;charset=utf-8," + encodeURIComponent(csv_data);
    temp_element.attr('href', address);
    temp_element.attr('download', 'submitty_user_emails.csv');
    temp_element.css('display', 'none');
    $(document.body).append(temp_element);
    $('#downloadlink')[0].click();
    $('#downloadlink').remove();
}

function adminTeamForm(new_team, who_id, reg_section, rot_section, user_assignment_setting_json, members, max_members) {
    $('.popup-form').css('display', 'none');
    var form = $("#admin-team-form");
    form.css("display", "block");

    $('[name="new_team"]', form).val(new_team);
    $('[name="reg_section"] option[value="' + reg_section + '"]', form).prop('selected', true);
    $('[name="rot_section"] option[value="' + rot_section + '"]', form).prop('selected', true);
    if(new_team) {
        $('[name="num_users"]', form).val(3);    
    }
    else if (!new_team) {
        $('[name="num_users"]', form).val(members.length+2);
    }

    var title_div = $("#admin-team-title");
    title_div.empty();
    var members_div = $("#admin-team-members");
    members_div.empty();
    var team_history_title_div = $("#admin-team-history-title");
    team_history_title_div.empty();
    var team_history_div_left = $("#admin-team-history-left");
    team_history_div_left.empty();
    var team_history_div_right = $("#admin-team-history-right");
    team_history_div_right.empty();
    members_div.append('Team Member IDs:<br />');
    var student_full = JSON.parse($('#student_full_id').val());
    if (new_team) {
        $('[name="new_team_user_id"]', form).val(who_id);
        $('[name="edit_team_team_id"]', form).val("");

        title_div.append('Create New Team: ' + who_id);
        members_div.append('<input class="readonly" type="text" name="user_id_0" readonly="readonly" value="' + who_id + '" />');
        for (var i = 1; i < 3; i++) {
            members_div.append('<input type="text" name="user_id_' + i + '" /><br />');
            $('[name="user_id_'+i+'"]', form).autocomplete({
                source: student_full
            });
        }
    }
    else {
        $('[name="new_team_user_id"]', form).val("");
        $('[name="edit_team_team_id"]', form).val(who_id);

        title_div.append('Edit Team: ' + who_id);
        for (var i = 0; i < members.length; i++) {
            members_div.append('<input class="readonly" type="text" name="user_id_' + i + '" readonly="readonly" value="' + members[i] + '" /> \
                <i id="remove_member_'+i+'" class="fa fa-times" onclick="removeTeamMemberInput('+i+');" style="color:red; cursor:pointer;" aria-hidden="true"></i><br />');
        }
        for (var i = members.length; i < (members.length+2); i++) {
            members_div.append('<input type="text" name="user_id_' + i + '" /><br />');
            $('[name="user_id_'+i+'"]', form).autocomplete({
                source: student_full
            });
        }
        var team_history_len=user_assignment_setting_json.team_history.length;
        team_history_title_div.append('Team History: ');
        team_history_div_left.append('<input class="readonly" type="text" style="width:100%;" name="team_formation_date_left" readonly="readonly" value="Team formed on: " /><br />');
        team_history_div_right.append('<input class="readonly" type="text" style="width:100%;" name="team_formation_date_right" readonly="readonly" value="' +user_assignment_setting_json.team_history[0].time+ '" /><br />');
        team_history_div_left.append('<input class="readonly" type="text" style="width:100%;" name="last_edit_left" readonly="readonly" value="Last edited on: " /><br />');
        team_history_div_right.append('<input class="readonly" type="text" style="width:100%;" name="last_edit_date_right" readonly="readonly" value="' +user_assignment_setting_json.team_history[team_history_len-1].time+ '" /><br />');
        for (var i = 0; i < members.length; i++) {
            for (var j = team_history_len-1; j >= 0; j--) {
                if(user_assignment_setting_json.team_history[j].action == "admin_add_user"){
                    if(user_assignment_setting_json.team_history[j].added_user == members[i]){
                        team_history_div_left.append('<input class="readonly" type="text" style="width:100%;" name="user_id_' +i+ '_left" readonly="readonly" value="'+members[i]+ ' added on: " /><br />');
                        team_history_div_right.append('<input class="readonly" type="text" style="width:100%;" name="user_id_' +i+ '_right" readonly="readonly" value="' +user_assignment_setting_json.team_history[j].time+ '" /><br />');
                    }
                }
                else if(user_assignment_setting_json.team_history[j].action == "admin_create"){
                    if(user_assignment_setting_json.team_history[j].first_user == members[i]){
                        team_history_div_left.append('<input class="readonly" type="text" style="width:100%;" name="user_id_' +i+ '_left" readonly="readonly" value="'+members[i]+ ' added on: " /><br />');
                        team_history_div_right.append('<input class="readonly" type="text" style="width:100%;" name="user_id_' +i+ '_right" readonly="readonly" value="' +user_assignment_setting_json.team_history[j].time+ '" /><br />');
                    }
                }
            }
        }
    }
    var param = (new_team ? 3 : members.length+2);
    members_div.append('<span style="cursor: pointer;" onclick="addTeamMemberInput(this, '+param+');"><i class="fa fa-plus-square" aria-hidden="true"></i> \
        Add More Users</span>');
}

function removeTeamMemberInput(i) {
    var form = $("#admin-team-form");
    $('[name="user_id_'+i+'"]', form).removeClass('readonly').removeAttr('readonly').val("");
    $("#remove_member_"+i).remove();
    var student_full = JSON.parse($('#student_full_id').val());
    $('[name="user_id_'+i+'"]', form).autocomplete({
        source: student_full
    });
}

function addTeamMemberInput(old, i) {
    old.remove()
    var form = $("#admin-team-form");
    $('[name="num_users"]', form).val( parseInt($('[name="num_users"]', form).val()) + 1);
    var members_div = $("#admin-team-members");
    members_div.append('<input type="text" name="user_id_' + i + '" /><br /> \
        <span style="cursor: pointer;" onclick="addTeamMemberInput(this, '+ (i+1) +');"><i class="fa fa-plus-square" aria-hidden="true"></i> \
        Add More Users</span>');
    var student_full = JSON.parse($('#student_full_id').val());
    $('[name="user_id_'+i+'"]', form).autocomplete({
        source: student_full
    });
}

function addCategory(old, i) {
    old.remove()
    var form = $("#admin-team-form");
    $('[name="num_users"]', form).val( parseInt($('[name="num_users"]', form).val()) + 1);
    var members_div = $("#admin-team-members");
    members_div.append('<input type="text" name="user_id_' + i + '" /><br /> \
        <span style="cursor: pointer;" onclick="addTeamMemberInput(this, '+ (i+1) +');"><i class="fa fa-plus-square" aria-hidden="true"></i> \
        Add More Users</span>');
    var student_full = JSON.parse($('#student_full_id').val());
    $('[name="user_id_'+i+'"]', form).autocomplete({
        source: student_full
    });
}

function importTeamForm() {
    $('.popup-form').css('display', 'none');
    var form = $("#import-team-form");
    form.css("display", "block");
    $('[name="upload_team"]', form).val(null);
}

/**
 * Toggles the page details box of the page, showing or not showing various information
 * such as number of queries run, length of time for script execution, and other details
 * useful for developers, but shouldn't be shown to normal users
 */
function togglePageDetails() {
    var element = document.getElementById('page-info');
    if (element.style.display === 'block') {
        element.style.display = 'none';
    }
    else {
        element.style.display = 'block';
        // Hide the box if you click outside of it
        document.body.addEventListener('mouseup', function pageInfo(event) {
            if (!element.contains(event.target)) {
                element.style.display = 'none';
                document.body.removeEventListener('mouseup', pageInfo, false);
            }
        });
    }
}

/**
 * Remove an alert message from display. This works for successes, warnings, or errors to the
 * user
 * @param elem
 */
function removeMessagePopup(elem) {
    $('#' + elem).fadeOut('slow', function() {
        $('#' + elem).remove();
    });
}

function gradeableChange(url, sel){
    url = url + sel.value;
    window.location.href = url;
}
function versionChange(url, sel){
    url = url + sel.value;
    window.location.href = url;
}

function checkVersionChange(days_late, late_days_allowed){
    if(days_late > late_days_allowed){
        var message = "The max late days allowed for this assignment is " + late_days_allowed + " days. ";
        message += "You are not supposed to change your active version after this time unless you have permission from the instructor. Are you sure you want to continue?";
        return confirm(message);
    }
    return true;
}

function checkTaVersionChange(){
    var message = "You are overriding the student's chosen submission. Are you sure you want to continue?";
    return confirm(message);
}

function checkVersionsUsed(gradeable, versions_used, versions_allowed) {
    versions_used = parseInt(versions_used);
    versions_allowed = parseInt(versions_allowed);
    if (versions_used >= versions_allowed) {
        return confirm("Are you sure you want to upload for " + gradeable + "? You have already used up all of your free submissions (" + versions_used + " / " + versions_allowed + "). Uploading may result in loss of points.");
    }
    return true;
}

function toggleDiv(id) {
    $("#" + id).toggle();
    return true;
}


function checkRefreshSubmissionPage(url) {
    setTimeout(function() {
        check_server(url)
    }, 1000);
}

function check_server(url) {
    $.post(url,
        function(data) {
            if (data.indexOf("REFRESH_ME") > -1) {
                location.reload(true);
            } else {
                checkRefreshSubmissionPage(url);
            }
        }
    );
}

function changeColor(div, hexColor){
    div.style.color = hexColor;
}

function openDiv(id) {
    var elem = $('#' + id);
    if (elem.hasClass('open')) {
        elem.hide();
        elem.removeClass('open');
        $('#' + id + '-span').removeClass('fa-folder-open').addClass('fa-folder');
    }
    else {
        elem.show();
        elem.addClass('open');
        $('#' + id + '-span').removeClass('fa-folder').addClass('fa-folder-open');
    }
    return false;
}

function openUrl(url) {
    window.open(url, "_blank", "toolbar=no, scrollbars=yes, resizable=yes, width=700, height=600");
    return false;
}

function openFrame(url, id, filename) {
    var iframe = $('#file_viewer_' + id);
    if (!iframe.hasClass('open')) {
        var iframeId = "file_viewer_" + id + "_iframe";
        // handle pdf
        if(filename.substring(filename.length - 3) === "pdf") {
            iframe.html("<iframe id='" + iframeId + "' src='" + url + "' width='750px' height='1200px' style='border: 0'></iframe>");
        }
        else {
            iframe.html("<iframe id='" + iframeId + "' onload='resizeFrame(\"" + iframeId + "\");' src='" + url + "' width='750px' style='border: 0'></iframe>");
        }
        iframe.addClass('open');
    }

    if (!iframe.hasClass('shown')) {
        iframe.show();
        iframe.addClass('shown');
        $($($(iframe.parent().children()[0]).children()[0]).children()[0]).removeClass('fa-plus-circle').addClass('fa-minus-circle');
    }
    else {
        iframe.hide();
        iframe.removeClass('shown');
        $($($(iframe.parent().children()[0]).children()[0]).children()[0]).removeClass('fa-minus-circle').addClass('fa-plus-circle');
    }
    return false;
}

function resizeFrame(id) {
    var height = parseInt($("iframe#" + id).contents().find("body").css('height').slice(0,-2));
    if (height > 500) {
        document.getElementById(id).height= "500px";
    }
    else {
        document.getElementById(id).height = (height+18) + "px";
    }
}

function batchImportJSON(url, csrf_token){
    $.ajax(url, {
        type: "POST",
        data: {
            csrf_token: csrf_token
        }
    })
    .done(function(response) {
        window.alert(response);
        location.reload(true);
    })
    .fail(function() {
        window.alert("[AJAX ERROR] Refresh page");
    });
}

/*
var hasNav = false;

function UpdateTableHeaders() {
    var count = 0;
    var scrollTop = parseInt($(window).scrollTop());
    $(".persist-area").each(function() {
        var el = $(".persist-thead", this);
        var height = parseFloat(el.height());
        var offset = parseFloat(el.offset().top);
        var floatingHeader = $(".floating-thead", this);
        if (scrollTop > (offset - height)) {
            if (floatingHeader.css("visibility") != "visible") {
                var cnt = 0;
                $("#floating-thead-0>td").each(function() {
                    $(this).css("width", $($("#anchor-thead").children()[cnt]).width());
                    cnt++;
                });
                floatingHeader.css("visibility", "visible");
            }
        }
        else {
            floatingHeader.css("visibility", "hidden");
        }
        $(".persist-header", this).each(function() {
            floatingHeader = $("#floating-header-" + count);
            el = $(this);
            height = parseFloat(el.height());
            offset = parseFloat(el.offset().top);
            if (scrollTop > (offset - height)) {
                if (floatingHeader.css("visibility") != "visible") {
                    floatingHeader.css("visibility", "visible");
                    var cnt = 0;
                    $("#floating-header-" + count + ">td").each(function() {
                        $(this).css("width", $($("#anchor-head-" + count).children()[cnt]).width());
                        cnt++;
                    });
                }
            }
            else {
                floatingHeader.css("visibility", "hidden");
            }
            count++;
        });
    });
}

$(function() {
    //hasNav = $("#nav").length > 0;
    hasNav = false; // nav doesn't float anymore so we don't have to account for it.
    // Each persist-area can have multiple persist-headers, we need to create each one with a new z-index
    var persist = $(".persist-area");
    var z_index = 900;
    var count = 0;
    persist.each(function() {
        var el = $(".persist-thead>tr", this);
        el.attr('id', 'anchor-thead');

        el.before(el.clone()).css({"width": el.width(), "top": "30px", "z-index": "899"}).addClass('floating-thead')
            .attr('id', 'floating-thead-' + count);
        $(".floating-thead", this).each(function() {
           $(this).children().removeAttr('width');
        });
        $(".persist-header", this).each(function() {
            $(this).attr('id', 'anchor-head-' + count);
            var clone = $(this);
            clone.before(clone.clone()).css({
                "width": clone.width(),
                "top": (30 + el.height()) + "px",
                "z-index": "" + z_index
            }).addClass("floating-header").removeClass("persist-header").attr('id', 'floating-header-' + count);
            z_index++;
            count++;
        });
    });

    if (persist.length > 0) {
        $(window).scroll(UpdateTableHeaders).trigger("scroll");
    }

    if (window.location.hash != "") {
        if ($(window.location.hash).offset().top > 0) {
            var minus = 60;
            if (hasNav) {
                minus += 30;
            }
            $("html, body").animate({scrollTop: ($(window.location.hash).offset().top - minus)}, 800);
        }
    }

    setTimeout(function() {
        $('.inner-message').fadeOut();
    }, 5000);
});
*/

function calcSimpleGraderStats(action) {
    var average = 0;        // overall average
    var stddev = 0;         // overall stddev
    var averages = [];      // average of each component
    var stddevs = [];       // stddev of each component
    var num_graded = 0;     // count how many students have a nonzero grade
    var c = 0;              // count the current component number
    var num_users = 0;      // count the number of users
    var has_graded = [];    // keeps track of whether or not each user already has a nonzero grade
    var elems;              // the elements of the current component
    var elem_type;          // the type of element that has the scores
    var data_attr;          // the data attribute in which the score is stored
    if(action == "lab")     {
        elem_type = "td";
        data_attr = "data-score";
    }
    else if(action == "numeric") {
        elem_type = "input";
        data_attr = "value";
    }
    else {
        console.log("Invalid grading type:");
        console.log(action);
        return;
    }
    // get all of the elements with the scores for the first component
    elems = $(elem_type + "[id^=cell-][id$=0]");
    while(elems.length > 0) {
        if(action == "lab" || elems.data('num') == true) {
            var sum = 0;                            // sum of the scores
            var sum_sqrs = 0;                       // sum of the squares of the scores
            var user_num = 0;                       // the index for has_graded so that it can be tracked whether or not there is a grade
            elems.each(function() {
                var has_section;
                if(action == "lab")     {
                    has_section = $(this).parent().find("td:nth-child(2)").text() != "";            // second child of parent has registration section as text
                }
                else if(action == "numeric") {
                    has_section = $(this).parent().parent().find("td:nth-child(2)").text() != "";   // second child of grandparent has registration section as text
                }

                if(has_section) {    
                    if(c == 0) {                                            // on the first iteration of the while loop...
                        num_users++;                                        // ...sum up the number of users...
                        has_graded.push(false);                             // ...and populate the has_graded array with false
                    }
                    var score = parseFloat($(this).attr(data_attr));
                    if(!has_graded[user_num]) {     // if they had no nonzero score previously...
                        has_graded[user_num] = score != 0;
                        if(has_graded[user_num]) {  // ...but they have one now
                            num_graded++;
                        }
                    }
                    sum += score;
                    sum_sqrs += score**2;
                }
                user_num++;
            });

            // calculate average and stddev from sums and sum_sqrs
            averages.push(sum/num_users);
            stddevs.push(Math.sqrt(Math.max(0, (sum_sqrs - sum**2 / num_users) / num_users)));
        }
        
        // get the elements for the next component
        elems = $(elem_type + "[id^=cell-][id$=" + (++c).toString() + "]");
    }

    // find total stats place all stats into their proper elements 
    var stats_popup = $("#simple-stats-popup");
    for(c = 0; c < averages.length; c++) {
        average += averages[c];
        stddev += stddevs[c]**2
        stats_popup.find("#avg-" + c.toString()).text(averages[c].toFixed(2));
        stats_popup.find("#stddev-" + c.toString()).text(stddevs[c].toFixed(2));
    }
    stddev = Math.sqrt(stddev);
    stats_popup.find("#avg-t").text(average.toFixed(2));
    stats_popup.find("#stddev-t").text(stddev.toFixed(2));

    var num_graded_elem = stats_popup.find("#num-graded");
    $(num_graded_elem).text(num_graded.toString() + "/" + num_users.toString() + " students have a nonzero grade.");
}


function showSimpleGraderStats(action) {
    if($("#simple-stats-popup").css("display") == "none") {
        calcSimpleGraderStats(action);
        $('.popup').css('display', 'none');
        $("#simple-stats-popup").css("display", "block");
        $(document).on("click", function(e) {                                           // event handler: when clicking on the document...
            if($(e.target).attr("id") != "simple-stats-btn"                             // ...if neither the stats button..
               && $(e.target).closest('div').attr('id') != "simple-stats-popup") {      // ...nor the stats popup are being clicked...
                $("#simple-stats-popup").css("display", "none");                        // ...hide the stats popup...
                $(document).off("click");                                               // ...and remove this event handler
            }
        });
    }
    else {
        $("#simple-stats-popup").css("display", "none");
        $(document).off("click");
    }
}

function updateCheckpointCell(elem, setFull) {
    elem = $(elem);
    if (!setFull && elem.data("score") === 1.0) {
        elem.data("score", 0.5);
        elem.css("background-color", "#88d0f4");
        elem.css("border-right", "15px solid #f9f9f9");
    }
    else if (!setFull && elem.data("score") === 0.5) {
        elem.data("score", 0);
        elem.css("background-color", "");
        elem.css("border-right", "15px solid #ddd");
    }
    else {
        elem.data("score", 1);
        elem.css("background-color", "#149bdf");
        elem.css("border-right", "15px solid #f9f9f9");
    }
}

function submitAJAX(url, data, callbackSuccess, callbackFailure) {
    $.ajax(url, {
        type: "POST",
        data: data
    })
    .done(function(response) {
        try{
            response = JSON.parse(response);
            if (response['status'] === 'success') {
                callbackSuccess(response);
            }
            else {
                console.log(response['message']);
                callbackFailure();
                if (response['status'] === 'error') {
                    window.alert("[SAVE ERROR] Refresh Page");
                }
            }
        }
        catch (e) {
            console.log(response);
            callbackFailure();
            window.alert("[SAVE ERROR] Refresh Page");
        }
    })
    .fail(function() {
        window.alert("[SAVE ERROR] Refresh Page");
    });
}

function setupCheckboxCells() {
    // Query for the <td> elements whose class attribute starts with "cell-"
    $("td[class^=cell-]").click(function() {
        var parent = $(this).parent();
        var elems = [];
        var scores = {};
        // If an entry in the User ID column is clicked, click all the checkpoint cells in that row
        if ($(this).hasClass('cell-all')) {
            var lastScore = null;
            var setFull = false;
            parent.children(".cell-grade").each(function() {
                updateCheckpointCell(this, setFull);
                elems.push(this);
            });
            parent.children(".cell-grade").each(function() {
                if (lastScore === null) {
                    lastScore = $(this).data("score");
                }
                else if (lastScore !== $(this).data("score")) {
                    setFull = true;
                }
                scores[$(this).data('id')] = $(this).data('score');
            });
        }
        // Otherwise, a single checkpoint cell was clicked
        else {
            updateCheckpointCell(this);
            elems.push(this);
            scores[$(this).data('id')] = $(this).data('score');
        }


        // Update the buttons to reflect that they were clicked
        submitAJAX(
            buildUrl({'component': 'grading', 'page': 'simple', 'action': 'save_lab'}),
            {
              'csrf_token': csrfToken,
              'user_id': parent.data("user"),
              'g_id': parent.data('gradeable'),
              'scores': scores
            },
            function() {
                elems.forEach(function(elem) {
                    elem = $(elem);
                    elem.animate({"border-right-width": "0px"}, 400);                                   // animate the box
                    elem.attr("data-score", elem.data("score"));                                        // update the score
                });
            },
            function() {
                elems.forEach(function(elem) {
                    console.log(elem);
                    $(elem).css("border-right-width", "15px");
                    $(elem).stop(true, true).animate({"border-right-color": "#DA4F49"}, 400);
                });
            }
        );
    });
}

$(function() {
    if (window.location.hash !== "") {
        if ($(window.location.hash).offset().top > 0) {
            var minus = 60;
            $("html, body").animate({scrollTop: ($(window.location.hash).offset().top - minus)}, 800);
        }
    }

    setTimeout(function() {
        $('.inner-message').fadeOut();
    }, 5000);

    var page_url = window.location.href;
    if(page_url.includes("page=simple")) {
        if(page_url.includes("action=lab")) {
            setupCheckboxCells();
            setupSimpleGrading('lab');
        }
        if(page_url.includes("action=numeric")) {
            setupNumericTextCells();
            setupSimpleGrading('numeric');
        }
    }
});

function setupNumericTextCells() {
    $("input[class=option-small-box]").change(function() {
        elem = this;
        if(this.value == 0){
            $(this).css("color", "#bbbbbb");
        }
        else{
            $(this).css("color", "");
        }
        var scores = {};
        var total = 0;
        $(this).parent().parent().children("td.option-small-input, td.option-small-output").each(function() {
            $(this).children(".option-small-box").each(function(){
                if($(this).data('num') === true){
                    total += parseFloat(this.value);
                }
                if($(this).data('total') === true){
                    this.value = total;
                }
                else{
                    scores[$(this).data("id")] = this.value;
                }
            });
        });

        // find number of users (num of input elements whose id starts with "cell-" and ends with 0)
        var num_users = 0;
        $("input[id^=cell-][id$=0]").each(function() {
            // increment only if great-grandparent id ends with a digit (indicates section is not NULL)
            if($(this).parent().parent().parent().attr("id").match(/\d+$/)) {
                num_users++;
            }
        });
        // find stats popup to access later
        var stats_popup = $("#simple-stats-popup");
        var num_graded_elem = stats_popup.find("#num-graded");

        submitAJAX(
            buildUrl({'component': 'grading', 'page': 'simple', 'action': 'save_numeric'}),
            {
                'csrf_token': csrfToken,
                'user_id': $(this).parent().parent().data("user"),
                'g_id': $(this).parent().parent().data('gradeable'),
                'scores': scores
            },
            function() {
                $(elem).css("background-color", "#ffffff");                                     // change the color
                $(elem).attr("value", elem.value);                                              // Stores the new input value
                $(elem).parent().parent().children("td.option-small-output").each(function() {  
                    $(this).children(".option-small-box").each(function() {
                        $(this).attr("value", this.value);                                      // Finds the element that stores the total and updates it to reflect increase
                    });
                });
            },
            function() {
                $(elem).css("background-color", "#ff7777");
            }
        );
    });

    $("input[class=csvButtonUpload]").change(function() {
        var confirmation = window.confirm("WARNING! \nPreviously entered data may be overwritten! " +
        "This action is irreversible! Are you sure you want to continue?\n\n Do not include a header row in your CSV. Format CSV using one column for " +
        "student id and one column for each field. Columns and field types must match.");
        if (confirmation) {
            var f = $('#csvUpload').get(0).files[0];
            if(f) {
                var reader = new FileReader();
                reader.readAsText(f);
                reader.onload = function(evt) {
                    var breakOut = false; //breakOut is used to break out of the function and alert the user the format is wrong
                    var lines = (reader.result).trim().split(/\r\n|\n/);
                    var tempArray = lines[0].split(',');
                    var csvLength = tempArray.length; //gets the length of the array, all the tempArray should be the same length
                    for (var k = 0; k < lines.length && !breakOut; k++) {
                        tempArray = lines[k].split(',');
                        breakOut = (tempArray.length === csvLength) ? false : true; //if tempArray is not the same length, break out
                    }
                    var textChecker = 0;
                    var num_numeric = 0;
                    var num_text = 0;
                    var user_ids = [];
                    var component_ids = [];
                    var get_once = true;
                    var gradeable_id = "";
                    if (!breakOut){
                        $('.cell-all').each(function() {
                            user_ids.push($(this).parent().data("user"));
                            if(get_once) {
                                num_numeric = $(this).parent().parent().data("numnumeric");
                                num_text = $(this).parent().parent().data("numtext");
                                component_ids = $(this).parent().parent().data("compids");
                                gradeable_id = $(this).parent().data("gradeable");
                                get_once = false;
                                if (csvLength !== 4 + num_numeric + num_text) {
                                    breakOut = true;
                                    return false;
                                }
                                var k = 3; //checks if the file has the right number of numerics
                                tempArray = lines[0].split(',');
                                if(num_numeric > 0) {
                                    for (k = 3; k < num_numeric + 4; k++) {
                                        if (isNaN(Number(tempArray[k]))) {
                                            breakOut = true;
                                            return false;
                                        }
                                    }
                                }

                                //checks if the file has the right number of texts
                                while (k < csvLength) {
                                    textChecker++;
                                    k++;
                                }
                                if (textChecker !== num_text) {
                                    breakOut = true;
                                    return false;
                                }
                            }
                        });
                    }
                    if (!breakOut){
                        submitAJAX(
                            buildUrl({'component': 'grading', 'page': 'simple', 'action': 'upload_csv_numeric'}),
                            {'csrf_token': csrfToken, 'g_id': gradeable_id, 'users': user_ids, 'component_ids' : component_ids,
                            'num_numeric' : num_numeric, 'num_text' : num_text, 'big_file': reader.result},
                            function(returned_data) {
                                $('.cell-all').each(function() {
                                    for (var x = 0; x < returned_data['data'].length; x++) {
                                        if ($(this).parent().data("user") === returned_data['data'][x]['username']) {
                                            var starting_index1 = 0;
                                            var starting_index2 = 3;
                                            var value_str = "value_";
                                            var status_str = "status_";
                                            var value_temp_str = "value_";
                                            var status_temp_str = "status_";
                                            var total = 0;
                                            var y = starting_index1;
                                            var z = starting_index2; //3 is the starting index of the grades in the csv
                                            //puts all the data in the form
                                            for (z = starting_index2; z < num_numeric + starting_index2; z++, y++) {
                                                value_temp_str = value_str + y;
                                                status_temp_str = status_str + y;
                                                $('#cell-'+$(this).parent().data("row")+'-'+(z-starting_index2)).val(returned_data['data'][x][value_temp_str]);
                                                if (returned_data['data'][x][status_temp_str] === "OK") {
                                                    $('#cell-'+$(this).parent().data("row")+'-'+(z-starting_index2)).css("background-color", "#ffffff");
                                                } else {
                                                    $('#cell-'+$(this).parent().data("row")+'-'+(z-starting_index2)).css("background-color", "#ff7777");
                                                }

                                                if($('#cell-'+$(this).parent().data("row")+'-'+(z-starting_index2)).val() == 0) {
                                                    $('#cell-'+$(this).parent().data("row")+'-'+(z-starting_index2)).css("color", "#bbbbbb");
                                                } else {
                                                    $('#cell-'+$(this).parent().data("row")+'-'+(z-starting_index2)).css("color", "");
                                                }

                                                total += Number($('#cell-'+$(this).parent().data("row")+'-'+(z-starting_index2)).val());
                                            }
                                            $('#total-'+$(this).parent().data("row")).val(total);
                                            z++;
                                            var counter = 0;
                                            while (counter < num_text) {
                                                value_temp_str = value_str + y;
                                                status_temp_str = status_str + y;
                                                $('#cell-'+$(this).parent().data("row")+'-'+(z-starting_index2-1)).val(returned_data['data'][x][value_temp_str]);
                                                z++;
                                                y++;
                                                counter++;
                                            }

                                            x = returned_data['data'].length;
                                        }
                                    }
                                });
                            },
                            function() {
                                alert("submission error");
                            }
                        );
                    }

                    if (breakOut) {
                        alert("CVS upload failed! Format file incorrect.");
                    }
                }
            }
        } else {
            var f = $('#csvUpload');
            f.replaceWith(f = f.clone(true));
        }
    });
}

function setupSimpleGrading(action) {

    // search bar code starts here (see site/app/templates/grading/StudentSearch.twig for #student-search)

    // updates the checkbox scores of elems:
    // if is_all, updates all, else, updates only the elem at idx
    // if !is_all and is the cell-all element, updates all the elements
    function updateCheckboxScores(num, elems, is_all, idx=0) {
        if(is_all) {                                // if updating all, update all non .cell-all cells individually
            elems.each(function() {
                if(!$(this).hasClass("cell-all")) {
                    updateCheckboxScores(num, elems, false, idx);
                }
                idx++;
            });
        }
        else {                              // if updating one, click until the score matches 
            elem = $(elems[idx]);
            if(!elem.hasClass("cell-all")) {
                for(var i = 0; i < 2; i++) {
                    if(elem.data("score") == num) {
                        break;
                    }
                    else {
                        elem.click();
                    }
                }
            }
            else {      // if it is .cell-all, update all instead
                updateCheckboxScores(num, elems, true);
            }
        } 
    }

    // highlights the first jquery-ui autocomplete result if there is only one
    function highlightOnSingleMatch(is_remove) {
        var matches = $("#student-search > ul > li");
        // if there is only one match, use jquery-ui css to highlight it so the user knows it is selected
        if(matches.length == 1) {
            $(matches[0]).children("div").addClass("ui-state-active");
        }
        else if(is_remove) {
            $(matches[0]).children("div").removeClass("ui-state-active");
        }
    }

    var dont_focus = true;                                          // set to allow toggling of focus on input element
    var num_rows = $("td.cell-all").length;                         // the number of rows in the table
    var search_bar_offset = $("#student-search").offset();          // the offset of the search bar: used to lock the searhc bar on scroll
    var highlight_color = "#337ab7";                                // the color used in the border around the selected element in the table
    var search_selector = action == 'lab'       ?                   // the selector being used varies depending on the action (lab/numeric are different)
                         'td[class^=cell-]'     :
                         'td.option-small-input';
    var table_row = 0;                                              // the current row
    var child_idx = 0;                                              // the index of the current element in the row
    var child_elems = $("tr[data-row=0]").find(search_selector);    // the clickable elements in the current row

    // outline the first element in the first row if able
    if(child_elems.length) {
        var child = $(child_elems[0]);
        if(action == 'numeric') {
            child = child.children("input");
        }
        child.css("outline", "3px dashed " + highlight_color);
    }

    // movement keybinds
    $(document).on("keydown", function(event) {
        if(!$("#student-search-input").is(":focus")) {
            // allow refocusing on the input field by pressing enter when it is not the focus
            if(event.keyCode == 13) {
                dont_focus = false;
            }
            // movement commands
            else if([37,38,39,40,9].includes(event.keyCode)) { // Arrow keys/tab unselect, bounds check, then move and reselect
                var child = $(child_elems[child_idx]);
                if(action == 'lab') {
                    child.css("outline", "");
                }
                else {
                    child.children("input").css("outline", "");
                }
                if(event.keyCode == 37 || (event.keyCode == 9 && event.shiftKey)) { // Left arrow/shift+tab
                    if(event.keyCode == 9 && event.shiftKey) {
                        event.preventDefault();
                    }
                    if(child_idx > 0 && (action == 'lab' || (event.keyCode == 9 && event.shiftKey) || child.children("input")[0].selectionStart == 0)) {
                        child_idx--;
                    }
                }
                else if(event.keyCode == 39 || event.keyCode == 9) {                // Right arrow/tab
                    if(event.keyCode == 9) {
                        event.preventDefault();
                    }
                    if(child_idx < child_elems.length - 1 && (action == 'lab' || event.keyCode == 9 || child.children("input")[0].selectionEnd == child.children("input")[0].value.length)) {
                        child_idx++;
                    }
                }
                else {
                    event.preventDefault();
                    if(event.keyCode == 38) {               // Up arrow
                        if(table_row > 0) {
                            table_row--;
                        }
                    }
                    else if(table_row < num_rows - 1) {     // Down arrow
                        table_row++;
                    }
                    child_elems = $("tr[data-row=" + table_row + "]").find(search_selector);
                }
                child = $(child_elems[child_idx]);
                if(action == 'lab') {
                    child.css("outline", "3px dashed " + highlight_color);
                }
                else {
                    child.children("input").css("outline", "3px dashed " + highlight_color).focus();
                }

                if((event.keyCode == 38 || event.keyCode == 40) && !child.isInViewport()) {
                    $('html, body').animate( { scrollTop: child.offset().top - $(window).height()/2}, 50);
                }
            }
        }
    });

    // refocus on the input field by pressing enter
    $(document).on("keyup", function(event) {
        if(event.keyCode == 13 && !dont_focus) {
            $("#student-search-input").focus();
        }
    });
    
    // register empty function locked event handlers for movement keybinds so they show up in the hotkeys menu
    registerKeyHandler({name: "Toggle Search", code: "Enter", locked: true}, function() {});
    registerKeyHandler({name: "Move Right", code: "ArrowRight", locked: true}, function() {});
    registerKeyHandler({name: "Move Left", code: "ArrowLeft", locked: true}, function() {});
    registerKeyHandler({name: "Move Up", code: "ArrowUp", locked: true}, function() {});
    registerKeyHandler({name: "Move Down", code: "ArrowDown", locked: true}, function() {});

    // register keybinds for grading controls
    if(action == 'lab') {
        registerKeyHandler({name: "Set Cell to 0", code: "KeyZ"}, function(event) {
            if(!$("#student-search-input").is(":focus")) {
                event.preventDefault();
                updateCheckboxScores(0, child_elems, false, child_idx);
            }
        });
        registerKeyHandler({name: "Set Cell to 0.5", code: "KeyX"}, function(event) {
            if(!$("#student-search-input").is(":focus")) {
                event.preventDefault();
                updateCheckboxScores(0.5, child_elems, false, child_idx);
            }
        });
        registerKeyHandler({name: "Set Cell to 1", code: "KeyC"}, function(event) {
            if(!$("#student-search-input").is(":focus")) {
                event.preventDefault();
                updateCheckboxScores(1, child_elems, false, child_idx);
            }
        });
        registerKeyHandler({name: "Cycle Cell Value", code: "KeyV"}, function(event) {
            if(!$("#student-search-input").is(":focus")) {
                event.preventDefault();
                $(child_elems[child_idx]).click();
            }
        });
        registerKeyHandler({name: "Set Row to 0", code: "KeyA"}, function(event) {
            if(!$("#student-search-input").is(":focus")) {
                event.preventDefault();
                updateCheckboxScores(0, child_elems, true);
            }
        });
        registerKeyHandler({name: "Set Row to 0.5", code: "KeyS"}, function(event) {
            if(!$("#student-search-input").is(":focus")) {
                event.preventDefault();
                updateCheckboxScores(0.5, child_elems, true);
            }
        });
        registerKeyHandler({name: "Set Row to 1", code: "KeyD"}, function(event) {
            if(!$("#student-search-input").is(":focus")) {
                event.preventDefault();
                updateCheckboxScores(1, child_elems, true);
            }
        });
        registerKeyHandler({name: "Cycle Row Value", code: "KeyF"}, function(event) {
            if(!$("#student-search-input").is(":focus")) {
                event.preventDefault();
                $(child_elems[0]).click();
            }
        });
    }
    // for numeric gradeables, whenever an input field is focused, update location variables
    else {
        $("input[id^=cell-]").on("focus", function(event) {
            $(child_elems[child_idx]).children("input").css("outline", "");
            var tr_elem = $(this).parent().parent();
            table_row = tr_elem.attr("data-row");
            child_elems = tr_elem.find(search_selector);
            child_idx = child_elems.index($(this).parent());
            $(child_elems[child_idx]).children("input").css("outline", "3px dashed " + highlight_color);
        });
    }

    // when pressing enter in the search bar, go to the corresponding element
    $("#student-search-input").on("keyup", function(event) {
        if(event.keyCode == 13) { // Enter
            this.blur();
            dont_focus = true; // dont allow refocusing until later
            var value = $(this).val();
            if(value != "") {
                var prev_child_elem = $(child_elems[child_idx]);
                // get the row number of the table element with the matching id
                var tr_elem = $('table tbody tr[data-user="' + value +'"]');
                // if a match is found, then use it to find the cell
                if(tr_elem.length > 0) {
                    table_row = tr_elem.attr("data-row");
                    child_elems = $("tr[data-row=" + table_row + "]").find(search_selector);
                    if(action == 'lab') {
                        prev_child_elem.css("outline", "");
                        $(child_elems[child_idx]).css("outline", "3px dashed " + highlight_color);
                    }
                    else {
                        prev_child_elem.children("input").css("outline", "");
                        $(child_elems[child_idx]).children("input").css("outline", "3px dashed " + highlight_color).focus();
                    }
                    $('html, body').animate( { scrollTop: $(child_elems).parent().offset().top - $(window).height()/2}, 50);
                }
                else {
                    // if no match is found and there is at least 1 matching autocomplete label, find its matching value
                    var first_match = $("#student-search > ul > li");
                    if(first_match.length == 1) {
                        var first_match_label = first_match.text();
                        var first_match_value = "";
                        for(var i = 0; i < student_full.length; i++) {      // NOTE: student_full comes from StudentSearch.twig script
                            if(student_full[i]["label"] == first_match_label) {
                                first_match_value = student_full[i]["value"];
                                break;
                            }
                        }
                        this.focus();
                        $(this).val(first_match_value); // reset the value...
                        $(this).trigger(event);    // ...and retrigger the event
                    }
                    else {
                        alert("ERROR:\n\nInvalid user.");
                        this.focus();                       // refocus on the input field
                    }
                }
            }
        }
    });

    $("#student-search-input").on("keydown", function() {
        highlightOnSingleMatch(false);
    });
    $("#student-search").on("DOMSubtreeModified", function() {
        highlightOnSingleMatch(true);
    });

    // clear the input field when it is focused
    $("#student-search-input").on("focus", function(event) {
        $(this).val("");
    });

    // used to reposition the search field when the window scrolls
    $(window).on("scroll", function(event) {
        var search_field = $("#student-search");
        if(search_bar_offset.top < $(window).scrollTop()) {
            search_field.css("top", 0);
            search_field.css("left", search_bar_offset.left);
            search_field.css("position", "fixed");
        }
        else {
            search_field.css("position", "relative");
            search_field.css("left", "");
        }
    });

    // check if the search field needs to be repositioned when the page is loaded
    if(search_bar_offset.top < $(window).scrollTop()) {
        var search_field = $("#student-search");
        search_field.css("top", 0);
        search_field.css("left", search_bar_offset.left);
        search_field.css("position", "fixed");
    }

    // check if the search field needs to be repositioned when the page is resized
    $(window).on("resize", function(event) {
        var settings_btn_offset = $("#settings-btn").offset();
        search_bar_offset = {   // NOTE: THE SEARCH BAR IS PLACED RELATIVE TO THE SETTINGS BUTTON
            top : settings_btn_offset.top,
            left : settings_btn_offset.left - $("#student-search").width()
        };
        if(search_bar_offset.top < $(window).scrollTop()) {
            var search_field = $("#student-search");
            search_field.css("top", 0);
            search_field.css("left", search_bar_offset.left);
            search_field.css("position", "fixed");
        }
    });

    // search bar code ends here
}

function getFileExtension(filename){
    return (filename.substring(filename.lastIndexOf(".")+1)).toLowerCase();
}

function openPopUp(css, title, count, testcase_num, side) {
    var element_id = "container_" + count + "_" + testcase_num + "_" + side;
    var elem_html = "<link rel=\"stylesheet\" type=\"text/css\" href=\"" + css + "\" />";
    elem_html += title + document.getElementById(element_id).innerHTML;
    my_window = window.open("", "_blank", "status=1,width=750,height=500");
    my_window.document.write(elem_html);
    my_window.document.close();
    my_window.focus();
}

function checkForumFileExtensions(files){
    var count = 0;
    for(var i = 0; i < files.length; i++){
        var extension = getFileExtension(files[i].name);
        if(extension == "gif" || extension == "png" || extension == "jpg" || extension == "jpeg" || extension == "bmp"){
            count++;
        }
    } return count == files.length;
}

function displayError(message){
    var message ='<div class="inner-message alert alert-error" style="position: fixed;top: 40px;left: 50%;width: 40%;margin-left: -20%;" id="theid"><a class="fa fa-times message-close" onClick="removeMessagePopup(\'theid\');"></a><i class="fa fa-times-circle"></i>' + message + '</div>';
    $('#messages').append(message);
    $('#messages').fadeIn("slow");
}

function resetForumFileUploadAfterError(displayPostId){
    $('#file_name' + displayPostId).html('');
    document.getElementById('file_input_label' + displayPostId).style.border = "2px solid red";
    document.getElementById('file_input' + displayPostId).value = null;
}

function checkNumFilesForumUpload(input, post_id){
    var displayPostId = (typeof post_id !== "undefined") ? "_" + escape(post_id) : "";
    if(input.files.length > 5){
        displayError('Max file upload size is 5. Please try again.');
        resetForumFileUploadAfterError(displayPostId);
    } else {
        if(!checkForumFileExtensions(input.files)){
            displayError('Invalid file type. Please upload only image files. (PNG, JPG, GIF, BMP...)');
            resetForumFileUploadAfterError(displayPostId);
            return;
        }
        $('#file_name' + displayPostId).html('<p style="display:inline-block;">' + input.files.length + ' files selected.</p>');
        $('#messages').fadeOut();
        document.getElementById('file_input_label' + displayPostId).style.border = "";
    }

}

function editPost(post_id, thread_id) {
     var url = buildUrl({'component': 'forum', 'page': 'get_edit_post_content'});
     $.ajax({
            url: url,
            type: "POST",
            data: {
                post_id: post_id,
                thread_id: thread_id
            },
            success: function(data){
                console.log(data);
                try {
                    var json = JSON.parse(data);
                } catch (err){
                    var message ='<div class="inner-message alert alert-error" style="position: fixed;top: 40px;left: 50%;width: 40%;margin-left: -20%;" id="theid"><a class="fa fa-times message-close" onClick="removeMessagePopup(\'theid\');"></a><i class="fa fa-times-circle"></i>Error parsing data. Please try again.</div>';
                    $('#messages').append(message);
                    return;
                }
                if(json['error']){
                    var message ='<div class="inner-message alert alert-error" style="position: fixed;top: 40px;left: 50%;width: 40%;margin-left: -20%;" id="theid"><a class="fa fa-times message-close" onClick="removeMessagePopup(\'theid\');"></a><i class="fa fa-times-circle"></i>' + json['error'] + '</div>';
                    $('#messages').append(message);
                    return;
                }
                var user_id = escape(json.user);
                var post_content = json.post;
                var time = (new Date(json.post_time));
                var date = time.toLocaleDateString();
                time = time.toLocaleString('en-US', { hour: 'numeric', minute: 'numeric', hour12: true });
                var contentBox = document.getElementById('edit_post_content');
                var editUserPrompt = document.getElementById('edit_user_prompt');
                editUserPrompt.innerHTML = 'Editing a post by: ' + user_id + ' on ' + date + ' at ' + time;
                contentBox.value = post_content;
                document.getElementById('edit_post_id').value = post_id;
                document.getElementById('edit_thread_id').value = thread_id;
                $('#edit-user-post').css('display', 'block');
            },
            error: function(){
                window.alert("Something went wrong while trying to edit the post. Please try again.");
            }
        });
}

function enableTabsInTextArea(id){
    var t = document.getElementById(id);

    $(t).on('input', function() {
        $(this).outerHeight(38).outerHeight(this.scrollHeight);
    });
    $(t).trigger('input');
        t.onkeydown = function(t){
            if(t.keyCode == 9){
                var text = this.value;
                var beforeCurse = this.selectionStart;
                var afterCurse = this.selectionEnd;
                this.value = text.substring(0, beforeCurse) + '\t' + text.substring(afterCurse);
                this.selectionStart = this.selectionEnd = beforeCurse+1;

                return false;

            }
        };

}

function changeDisplayOptions(option, thread_id){
    window.location.replace(buildUrl({'component': 'forum', 'page': 'view_thread', 'option': option, 'thread_id': thread_id}));
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

function modifyThreadList(currentThreadId, currentCategoriesId){
    var categories_value = $("#thread_category").val();
    categories_value = (categories_value == null)?"":categories_value.join("|");
    var url = buildUrl({'component': 'forum', 'page': 'get_threads'});
    $.ajax({
            url: url,
            type: "POST",
            data: {
                thread_categories: categories_value,
                currentThreadId: currentThreadId,
                currentCategoriesId: currentCategoriesId
            },
            success: function(r){
               var x = JSON.parse(r).html;
               x = `${x}`;
               $(".thread_list").html(x);
            },
            error: function(){
                window.alert("Something went wrong when trying to filter. Please try again.");
            }
    })
}

function replyPost(post_id){
    if ( $('#'+ post_id + '-reply').css('display') == 'block' ){
        $('#'+ post_id + '-reply').css("display","none");
    } else {
        hideReplies();
        $('#'+ post_id + '-reply').css('display', 'block');
    }
}

function addNewCategory(){
    var newCategory = $("#new_category_text").val();
    var url = buildUrl({'component': 'forum', 'page': 'add_category'});
    $.ajax({
            url: url,
            type: "POST",
            data: {
                newCategory: newCategory
            },
            success: function(data){
                try {
                    var json = JSON.parse(data);
                } catch (err){
                    var message ='<div class="inner-message alert alert-error" style="position: fixed;top: 40px;left: 50%;width: 40%;margin-left: -20%;" id="theid"><a class="fa fa-times message-close" onClick="removeMessagePopup(\'theid\');"></a><i class="fa fa-times-circle"></i>Error parsing data. Please try again.</div>';
                    $('#messages').append(message);
                    return;
                }
                if(json['error']){
                    var message ='<div class="inner-message alert alert-error" style="position: fixed;top: 40px;left: 50%;width: 40%;margin-left: -20%;" id="theid"><a class="fa fa-times message-close" onClick="removeMessagePopup(\'theid\');"></a><i class="fa fa-times-circle"></i>' + json['error'] + '</div>';
                    $('#messages').append(message);
                    return;
                }
                var message ='<div class="inner-message alert alert-success" style="position: fixed;top: 40px;left: 50%;width: 40%;margin-left: -20%;" id="theid"><a class="fa fa-times message-close" onClick="removeMessagePopup(\'theid\');"></a><i class="fa fa-times-circle"></i>Successfully created category '+ escape(newCategory) +'.</div>';
                $('#messages').append(message);
                $('#new_category_text').val("");
                $('#cat').append('<option value="' + json['new_id'] + '">' + escape(newCategory) +'</option>');
            },
            error: function(){
                window.alert("Something went wrong while trying to add a new category. Please try again.");
            }
    })
}

/*This function ensures that only one reply box is open at a time*/
function hideReplies(){
    var hide_replies = document.getElementsByClassName("reply-box");
    for(var i = 0; i < hide_replies.length; i++){
        hide_replies[i].style.display = "none";
    }
}

/*This function makes sure that only posts with children will have the collapse function*/
function addCollapsable(){
    var posts = $(".post_box").toArray();
    for(var i = 1; i < posts.length; i++){
        if(parseInt($(posts[i]).next().next().attr("reply-level")) > parseInt($(posts[i]).attr("reply-level"))){
            $(posts[i]).find(".expand")[0].innerHTML = "Hide Replies";
        } else {
            var button = $(posts[i]).find(".expand")[0];
            $(button).hide();
        }
    }
}

function hidePosts(text, id) {
    var currentLevel = parseInt($(text).parent().parent().attr("reply-level")); //The double parent is here because the button is in a span, which is a child of the main post.
    var selector = $(text).parent().parent().next().next();
    var counter = 0;
    var parent_status = "Hide Replies";``
    if (text.innerHTML != "Hide Replies") {
        text.innerHTML = "Hide Replies";
        while (selector.attr("reply-level") > currentLevel) {
            $(selector).show();
            if($(selector).find(".expand")[0].innerHTML != "Hide Replies"){
                var nextLvl = parseInt($(selector).next().next().attr("reply-level"));
                while(nextLvl > (currentLevel+1)){
                    selector = $(selector).next().next();
                    nextLvl = $(selector).next().next().attr("reply-level");
                }
            }
            selector = $(selector).next().next();
        }

    } else {
        while (selector.attr("reply-level") > currentLevel) {
            $(selector).hide();
            selector = $(selector).next().next();
            counter++;
        }
        if(counter != 0){
            text.innerHTML = "Show " + ((counter > 1) ? (counter + " Replies") : "Reply");
        } else {
            text.innerHTML = "Hide Replies";
        }
    }

}

function deletePost(thread_id, post_id, author, time){
    var confirm = window.confirm("Are you sure you would like to delete this post?: \n\nWritten by:  " + author + "  @  " + time + "\n\nPlease note: The replies to this comment will also be deleted. \n\nIf you are deleting the first post in a thread this will delete the entire thread.");
    if(confirm){
        var url = buildUrl({'component': 'forum', 'page': 'delete_post'});
        $.ajax({
            url: url,
            type: "POST",
            data: {
                post_id: post_id,
                thread_id: thread_id
            },
            success: function(data){
                try {
                    var json = JSON.parse(data);
                } catch (err){
                    var message ='<div class="inner-message alert alert-error" style="position: fixed;top: 40px;left: 50%;width: 40%;margin-left: -20%;" id="theid"><a class="fa fa-times message-close" onClick="removeMessagePopup(\'theid\');"></a><i class="fa fa-times-circle"></i>Error parsing data. Please try again.</div>';
                    $('#messages').append(message);
                    return;
                }
                if(json['error']){
                    var message ='<div class="inner-message alert alert-error" style="position: fixed;top: 40px;left: 50%;width: 40%;margin-left: -20%;" id="theid"><a class="fa fa-times message-close" onClick="removeMessagePopup(\'theid\');"></a><i class="fa fa-times-circle"></i>' + json['error'] + '</div>';
                    $('#messages').append(message);
                    return;
                }
                var new_url = "";
                switch(json['type']){
                    case "thread":
                    default:
                        new_url = buildUrl({'component': 'forum', 'page': 'view_thread'});
                    break;


                    case "post":
                        new_url = buildUrl({'component': 'forum', 'page': 'view_thread', 'thread_id': thread_id});
                    break;
                }
                window.location.replace(new_url);
            },
            error: function(){
                window.alert("Something went wrong while trying to delete post. Please try again.");
            }
        })
    }
}

function alterAnnouncement(thread_id, confirmString, url){
    var confirm = window.confirm(confirmString);
    if(confirm){
        var url = buildUrl({'component': 'forum', 'page': url});
        $.ajax({
            url: url,
            type: "POST",
            data: {
                thread_id: thread_id
            },
            success: function(data){
                window.location.replace(buildUrl({'component': 'forum', 'page': 'view_thread', 'thread_id': thread_id}));
            },
            error: function(){
                window.alert("Something went wrong while trying to remove announcement. Please try again.");
            }
        })
    }
}

function pinThread(thread_id, url){
    var url = buildUrl({'component': 'forum', 'page': url});
    $.ajax({
        url: url,
        type: "POST",
        data: {
            thread_id: thread_id
        },
        success: function(data){
            window.location.replace(buildUrl({'component': 'forum', 'page': 'view_thread', 'thread_id': thread_id}));
        },
        error: function(){
            window.alert("Something went wrong while trying on pin/unpin thread. Please try again.");
        }
    });
}

function updateHomeworkExtensions(data) {
    var fd = new FormData($('#excusedAbsenceForm').get(0));
    var url = buildUrl({'component': 'admin', 'page': 'late', 'action': 'update_extension'});
    $.ajax({
        url: url,
        type: "POST",
        data: fd,
        processData: false,
        cache: false,
        contentType: false,
        success: function(data) {
            try {
                var json = JSON.parse(data);
            } catch(err){
                var message ='<div class="inner-message alert alert-error" style="position: fixed;top: 40px;left: 50%;width: 40%;margin-left: -20%;" id="theid"><a class="fa fa-times message-close" onClick="removeMessagePopup(\'theid\');"></a><i class="fa fa-times-circle"></i>Error parsing data. Please try again.</div>';
                $('#messages').append(message);
                return;
            }
            if(json['error']){
                var message ='<div class="inner-message alert alert-error" style="position: fixed;top: 40px;left: 50%;width: 40%;margin-left: -20%;" id="theid"><a class="fa fa-times message-close" onClick="removeMessagePopup(\'theid\');"></a><i class="fa fa-times-circle"></i>' + json['error'] + '</div>';
                $('#messages').append(message);
                return;
            }
            var form = $("#load-homework-extensions");
            $('#my_table tr:gt(0)').remove();
            var title = '<div class="option-title" id="title">Current Extensions for ' + json['gradeable_id'] + '</div>';
            $('#title').replaceWith(title);
            if(json['users'].length === 0){
                $('#my_table').append('<tr><td colspan="4">There are no extensions for this homework</td></tr>');
            }
            json['users'].forEach(function(elem){
                var bits = ['<tr><td>' + elem['user_id'], elem['user_firstname'], elem['user_lastname'], elem['late_day_exceptions'] + '</td></tr>'];
                $('#my_table').append(bits.join('</td><td>'));
            });
            $('#user_id').val(this.defaultValue);
            $('#late_days').val(this.defaultValue);
            $('#csv_upload').val(this.defaultValue);
            var message ='<div class="inner-message alert alert-success" style="position: fixed;top: 40px;left: 50%;width: 40%;margin-left: -20%;" id="theid"><a class="fa fa-times message-close" onClick="removeMessagePopup(\'theid\');"></a><i class="fa fa-times-circle"></i>Updated exceptions for ' + json['gradeable_id'] + '.</div>';
            $('#messages').append(message);
        },
        error: function() {
            window.alert("Something went wrong. Please try again.");
        }
    })
    return false;
}

function loadHomeworkExtensions(g_id) {
    var url = buildUrl({'component': 'admin', 'page': 'late', 'action': 'get_extension_details', 'g_id': g_id});
    $.ajax({
        url: url,
        success: function(data) {
            var json = JSON.parse(data);
            var form = $("#load-homework-extensions");
            $('#my_table tr:gt(0)').remove();
            var title = '<div class="option-title" id="title">Current Extensions for ' + json['gradeable_id'] + '</div>';
            $('#title').replaceWith(title);
            if(json['users'].length === 0){
                $('#my_table').append('<tr><td colspan="4">There are no extensions for this homework</td></tr>');
            }
            json['users'].forEach(function(elem){
                var bits = ['<tr><td>' + elem['user_id'], elem['user_firstname'], elem['user_lastname'], elem['late_day_exceptions'] + '</td></tr>'];
                $('#my_table').append(bits.join('</td><td>'));
            });
        },
        error: function() {
            window.alert("Something went wrong. Please try again.");
        }
    });
}

function addBBCode(type, divTitle){
    var cursor = $(divTitle).prop('selectionStart');
    var text = $(divTitle).val();
    var insert = "";
    if(type == 1) {
        insert = "[url=http://example.com]display text[/url]";
    } else if(type == 0){
        insert = "[code][/code]";
    }
    $(divTitle).val(text.substring(0, cursor) + insert + text.substring(cursor));
}

function refreshOnResponseLateDays(json) {
    $('#late_day_table tr:gt(0)').remove();
    if(json['users'].length === 0){
        $('#late_day_table').append('<tr><td colspan="6">No late days are currently entered.</td></tr>');
    }
    json['users'].forEach(function(elem){
        elem_delete = "<a onclick=\"deleteLateDays('"+elem['user_id']+"', '"+elem['datestamp']+"');\"><i class='fa fa-close'></i></a>";
        var bits = ['<tr><td>' + elem['user_id'], elem['user_firstname'], elem['user_lastname'], elem['late_days'], elem['datestamp'], elem_delete + '</td></tr>'];
        $('#late_day_table').append(bits.join('</td><td>'));
    });
}

function updateLateDays(data) {
    var fd = new FormData($('#lateDayForm').get(0));
    var selected_csv_option = $("input:radio[name=csv_option]:checked").val();
    var url = buildUrl({'component': 'admin', 'page': 'late', 'action': 'update_late', 'csv_option': selected_csv_option});
    $.ajax({
        url: url,
        type: "POST",
        data: fd,
        processData: false,
        contentType: false,
        success: function(data) {
            var json = JSON.parse(data);
            if(json['error']){
                var message ='<div class="inner-message alert alert-error" style="position: fixed;top: 40px;left: 50%;width: 40%;margin-left: -20%;" id="theid"><a class="fa fa-times message-close" onClick="removeMessagePopup(\'theid\');"></a><i class="fa fa-times-circle"></i>' + json['error'] + '</div>';
                $('#messages').append(message);
                return;
            }
            var form = $("#load-late-days");
            refreshOnResponseLateDays(json);
            //Reset all form elements
            $('#user_id').val(this.defaultValue);
            $('#datestamp').val(this.defaultValue);
            $('#late_days').val(this.defaultValue);
            $('#csv_upload').val(this.defaultValue);
            $('#csv_option_overwrite_all').prop('checked',true);
            //Display confirmation message
            var message ='<div class="inner-message alert alert-success" style="position: fixed;top: 40px;left: 50%;width: 40%;margin-left: -20%;" id="theid"><a class="fa fa-times message-close" onClick="removeMessagePopup(\'theid\');"></a><i class="fa fa-times-circle"></i>Late days have been updated.</div>';
            $('#messages').append(message);
        },
        error: function() {
            window.alert("Something went wrong. Please try again.");
        }
    })
    return false;
}

function deleteLateDays(user_id, datestamp) {
    // Convert 'MM/DD/YYYY HH:MM:SS A' to 'MM/DD/YYYY'
    datestamp_mmddyy = datestamp.split(" ")[0];
    var url = buildUrl({'component': 'admin', 'page': 'late', 'action': 'delete_late'});
    var confirm = window.confirm("Are you sure you would like to delete this entry?");
    if (confirm) {
        $.ajax({
            url: url,
            type: "POST",
            data: {
                csrf_token: csrfToken,
                user_id: user_id,
                datestamp: datestamp_mmddyy
            },
            success: function(data) {
                var json = JSON.parse(data);
                if(json['error']){
                    var message ='<div class="inner-message alert alert-error" style="position: fixed;top: 40px;left: 50%;width: 40%;margin-left: -20%;" id="theid"><a class="fa fa-times message-close" onClick="removeMessagePopup(\'theid\');"></a><i class="fa fa-times-circle"></i>' + json['error'] + '</div>';
                    $('#messages').append(message);
                    return;
                }
                refreshOnResponseLateDays(json);
                var message ='<div class="inner-message alert alert-success" style="position: fixed;top: 40px;left: 50%;width: 40%;margin-left: -20%;" id="theid"><a class="fa fa-times message-close" onClick="removeMessagePopup(\'theid\');"></a><i class="fa fa-times-circle"></i>Late days entry removed.</div>';
                $('#messages').append(message);
            },
            error: function() {
                window.alert("Something went wrong. Please try again.");
            }
        })
    }
    return false;
}

/**
  * Taken from: https://stackoverflow.com/questions/1787322/htmlspecialchars-equivalent-in-javascript
  */
function escapeSpecialChars(text) {
  var map = {
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    '"': '&quot;',
    "'": '&#039;'
  };

  return text.replace(/[&<>"']/g, function(m) { return map[m]; });
}

function escapeHTML(str) {
    return str.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}


// edited slightly from https://stackoverflow.com/a/40658647
// returns a boolean value indicating whether or not the element is entirely in the viewport
// i.e. returns false iff there is some part of the element outside the viewport
$.fn.isInViewport = function() {                                        // jQuery method: use as $(selector).isInViewPort()
    var elementTop = $(this).offset().top;                              // get top offset of element
    var elementBottom = elementTop + $(this).outerHeight();             // add height to top to get bottom

    var viewportTop = $(window).scrollTop();                            // get top of window
    var viewportBottom = viewportTop + $(window).height();              // add height to get bottom

    return elementTop > viewportTop && elementBottom < viewportBottom;
};