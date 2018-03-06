var siteUrl = undefined;
var csrfToken = undefined;

function setSiteDetails(url, setCsrfToken) {
    siteUrl = url;
    csrfToken = setCsrfToken;
}

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
    var url = siteUrl;
    var constructed = "";
    for (var part in parts) {
        if (parts.hasOwnProperty(part)) {
            constructed += "&" + part + "=" + parts[part];
        }
    }
    return url + constructed;
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

function adminTeamForm(new_team, who_id, section, members, max_members) {
    $('.popup-form').css('display', 'none');
    var form = $("#admin-team-form");
    form.css("display", "block");

    $('[name="new_team"]', form).val(new_team);
    $('[name="section"] option[value="' + section + '"]', form).prop('selected', true);
    $('[name="num_users"]', form).val(max_members);

    var title_div = $("#admin-team-title");
    title_div.empty();
    var members_div = $("#admin-team-members");
    members_div.empty();
    members_div.append('Team Member IDs:<br />');

    if (new_team) {
        $('[name="new_team_user_id"]', form).val(who_id);
        $('[name="edit_team_team_id"]', form).val("");

        title_div.append('Create New Team: ' + who_id);
        members_div.append('<input class="readonly" type="text" name="user_id_0" readonly="readonly" value="' + who_id + '" />');
        for (var i = 1; i < max_members; i++) {
            members_div.append('<input type="text" name="user_id_' + i + '" /><br />');
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
        for (var i = members.length; i < max_members; i++) {
            members_div.append('<input type="text" name="user_id_' + i + '" /><br />');
        }
    }

    members_div.append('<span style="cursor: pointer;" onclick="addTeamMemberInput(this, '+max_members+');"><i class="fa fa-plus-square" aria-hidden="true"></i> \
        Add More Users</span>');
}

function removeTeamMemberInput(i) {
    var form = $("#admin-team-form");
    $('[name="user_id_'+i+'"]', form).removeClass('readonly').removeAttr('readonly').val("");
    $("#remove_member_"+i).remove();
}

function addTeamMemberInput(old, i) {
    old.remove()
    var form = $("#admin-team-form");
    $('[name="num_users"]', form).val( parseInt($('[name="num_users"]', form).val()) + 1);
    var members_div = $("#admin-team-members");
    members_div.append('<input type="text" name="user_id_' + i + '" /><br /> \
        <span style="cursor: pointer;" onclick="addTeamMemberInput(this, '+ (i+1) +');"><i class="fa fa-plus-square" aria-hidden="true"></i> \
        Add More Users</span>');
}

/**
 * Toggles the page details box of the page, showing or not showing various information
 * such as number of queries run, length of time for script execution, and other details
 * useful for developers, but shouldn't be shown to normal users
 */
function togglePageDetails() {
    if (document.getElementById('page-info').style.visibility == 'visible') {
        document.getElementById('page-info').style.visibility = 'hidden';
    }
    else {
        document.getElementById('page-info').style.visibility = 'visible';
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
    $("td[class^=cell-]").click(function() {
        var parent = $(this).parent();
        var elems = [];
        var scores = {};
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
        else {
            updateCheckpointCell(this);
            elems.push(this);
            scores[$(this).data('id')] = $(this).data('score');
        }
        
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
                    $(elem).animate({"border-right-width": "0px"}, 400);
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

function setupNumericTextCells() {
  $("input[class=option-small-box]").keydown(function(key){
    var cell=this.id.split('-');
    // right
    if(key.keyCode === 39){
      if(this.selectionEnd == this.value.length){
        $('#cell-'+cell[1]+'-'+(++cell[2])).focus();
      }
    }
    // left
    else if(key.keyCode == 37){
      if(this.selectionStart == 0){
        $('#cell-'+cell[1]+'-'+(--cell[2])).focus();
      }
    }
    // up
    else if(key.keyCode == 38){
      $('#cell-'+(--cell[1])+'-'+cell[2]).focus();

    }
    // down
    else if(key.keyCode == 40){
      $('#cell-'+(++cell[1])+'-'+cell[2]).focus();
    }
  });

  $("input[class=option-small-box]").change(function() {
    var elem = this;
    if(this.value == 0){
      $(this).css("color", "#bbbbbb");
    }
    else{
      $(this).css("color", "");
    }
    var scores = {};
    var total = 0;
    var parent = $(this).parent().parent();
    scores[$(this).data('id')] = this.value;

    $(this).parent().parent().children("td.option-small-input, td.option-small-output").each(function() {
      $(this).children(".option-small-box").each(function(){
        if($(this).data('num') === true){
          total += parseFloat(this.value);
        }
        if($(this).data('total') === true){
          this.value = total;
        }
      });
    });

    submitAJAX(
      buildUrl({'component': 'grading', 'page': 'simple', 'action': 'save_numeric'}),
      {
        'csrf_token': csrfToken,
        'user_id': parent.data('user'),
        'g_id': parent.data('gradeable'),
        scores: scores
      },
      function() {
        $(elem).css("background-color", "#ffffff");
      },
      function() {
        $(elem).css("background-color", "#ff7777");
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

    setupCheckboxCells();
    setupNumericTextCells();
});

function setupNumericTextCells() {
    $("input[class=option-small-box]").keydown(function(key){
        var cell=this.id.split('-');
        // right
        if(key.keyCode === 39){
            if(this.selectionEnd == this.value.length){
                $('#cell-'+cell[1]+'-'+(++cell[2])).focus();
            }
        }
        // left
        else if(key.keyCode == 37){
            if(this.selectionStart == 0){
                $('#cell-'+cell[1]+'-'+(--cell[2])).focus();
            }
        }
        // up
        else if(key.keyCode == 38){
            $('#cell-'+(--cell[1])+'-'+cell[2]).focus();

        }
        // down
        else if(key.keyCode == 40){
            $('#cell-'+(++cell[1])+'-'+cell[2]).focus();
        }
    });

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

        submitAJAX(
            buildUrl({'component': 'grading', 'page': 'simple', 'action': 'save_numeric'}),
            {'csrf_token': csrfToken, 'user_id': $(this).parent().parent().data("user"), 'g_id': $(this).parent().parent().data('gradeable'), 'scores': scores},
            function() {
                $(elem).css("background-color", "#ffffff");
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

function openPopUp(css, title, count, testcase_num, side) {
    var element_id = "container_" + count + "_" + testcase_num + "_" + side;
    var elem_html = "<link rel=\"stylesheet\" type=\"text/css\" href=\"" + css + "\" />"
    elem_html += title + document.getElementById(element_id).innerHTML;
    my_window = window.open("", "_blank", "status=1,width=750,height=500");
    my_window.document.write(elem_html);
    my_window.document.close(); 
    my_window.focus();
}

function checkNumFilesForumUpload(input){
    if(input.files.length > 5){
        $('#file_name').html('');
        document.getElementById('file_input_label').style.border = "2px solid red";
        var message ='<div class="inner-message alert alert-error" style="position: fixed;top: 40px;left: 50%;width: 40%;margin-left: -20%;" id="theid"><a class="fa fa-times message-close" onClick="removeMessagePopup(\'theid\');"></a><i class="fa fa-times-circle"></i>Max file upload size is 5. Please try again.</div>';
        $('#messages').append(message);
        document.getElementById('file_input').value = null;
    } else {
        $('#file_name').html('<p style="display:inline-block;">' + input.files.length + ' files selected.</p>');
        $('#messages').fadeOut();
        document.getElementById('file_input_label').style.border = "";
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
                contentBox.innerHTML = post_content;
                document.getElementById('edit_post_id').value = post_id;
                document.getElementById('edit_thread_id').value = thread_id;
                $('.popup-form').css('display', 'block');
                
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

function resetScrollPosition(){
    if(sessionStorage.scrollTop != "undefined") {
        sessionStorage.scrollTop = undefined;
    }
}

function saveScrollLocationOnRefresh(className){
    var element = document.getElementsByClassName(className);
    $(element).scroll(function() {
        sessionStorage.scrollTop = $(this).scrollTop();
    });
    $(document).ready(function() {
        if(sessionStorage.scrollTop != "undefined"){
            $(element).scrollTop(sessionStorage.scrollTop);
        }
    });
}

function deletePost(thread_id, post_id, author, time){
    var confirm = window.confirm("Are you sure you would like to delete this post?: \n\nWritten by:  " + author + "  @  " + time + "\n\nPlease note:  If you are deleting the first post in a thread this will delete the entire thread.");
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

function updateLateDays(data) {
    var fd = new FormData($('#lateDayForm').get(0));
    var url = buildUrl({'component': 'admin', 'page': 'late', 'action': 'update_late'});
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
            $('#late_day_table tr:gt(0)').remove();
            if(json['users'].length === 0){
                $('#late_day_table').append('<tr><td colspan="4">No late days are currently entered.</td></tr>');
            }
            json['users'].forEach(function(elem){
                var bits = ['<tr><td>' + elem['user_id'], elem['user_firstname'], elem['user_lastname'], elem['late_days'], elem['datestamp'] + '</td></tr>'];
                $('#late_day_table').append(bits.join('</td><td>'));
            });
            $('#user_id').val(this.defaultValue);
            $('#datestamp').val(this.defaultValue);
            $('#late_days').val(this.defaultValue);
            $('#csv_upload').val(this.defaultValue);
            var message ='<div class="inner-message alert alert-success" style="position: fixed;top: 40px;left: 50%;width: 40%;margin-left: -20%;" id="theid"><a class="fa fa-times message-close" onClick="removeMessagePopup(\'theid\');"></a><i class="fa fa-times-circle"></i>Late days have been updated.</div>';
            $('#messages').append(message);
        },
        error: function() {
            window.alert("Something went wrong. Please try again.");
        }
    })
    return false;
}

function escapeHTML(str) {
    return str.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}
