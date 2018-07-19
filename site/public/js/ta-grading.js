//Used to reset users cookies
var cookie_version = 1;

var editModeEnabled = false;
//Check if cookie version is/is not the same as the current version
var versionMatch = false;
//Set positions and visibility of configurable ui elements
$(function() {
    //Check each cookie and test for 'undefined'. If any cookie is undefined
    $.each(document.cookie.split(/; */), function(){
        var cookie = this.split("=")
        if(!cookie[1] || cookie[1] == 'undefined'){
            deleteCookies();
        }
        else if(cookie[0] === "cookie_version"){
            if(cookie[1] == cookie_version){
                versionMatch = true;
            }
        }
    });
    if(!versionMatch) {
        //If cookie version is not the same as the current version then toggle the visibility of each
        //rubric panel then update the cookies
        deleteCookies();
        setAutogradingVisible(true);
        setRubricVisible(true);
        setSubmissionsVisible(true);
        setInfoVisible(true);
        resetModules();
        updateCookies();
    }
   else{
        readCookies();
        updateCookies();
    }

    $('body').css({'position':'fixed', 'width':'100%'});
    if(getGradeable()!=null){
        calculatePercentageTotal();
    }
    var progressbar = $(".progressbar"),
        value = progressbar.val();
    $(".progress-value").html("<b>" + value + '%</b>');


    $( ".draggable" ).draggable({snap:false, grid:[2, 2], stack:".draggable"}).resizable();

    $("#bar_wrapper").resizable("destroy"); //We don't want the toolbar to be resizable

    $(".draggable").on("dragstop", function(){
        $('#bar_wrapper').css({'z-index':'40'}); //Reset z-index after jquery drag
        updateCookies();
    });

    $(".draggable").on("resizestop", function(){
        updateCookies();
    });

    eraseCookie("reset");
});

function createCookie(name,value,seconds)  {
    if(seconds) {
        var date = new Date();
        date.setTime(date.getTime()+(seconds*1000));
        var expires = "; expires="+date.toGMTString();
    }
    else var expires = "";
    document.cookie = name+"="+value+expires+"; path=/";
}

function eraseCookie(name) {
    createCookie(name,"",-3600);
}

function deleteCookies(){
    $.each(document.cookie.split(/; */), function(){
        var cookie = this.split("=")
        if(!cookie[1] || cookie[1] == 'undefined'){
            document.cookie = cookie[0] + '=; expires=Thu, 01 Jan 1970 00:00:01 GMT;';
            document.cookie = "cookie_version=-1; path=/;";
        }
    });
}

function onAjaxInit() {}

function readCookies(){
    var output_top = document.cookie.replace(/(?:(?:^|.*;\s*)output_top\s*\=\s*([^;]*).*$)|^.*$/, "$1");
    var output_left = document.cookie.replace(/(?:(?:^|.*;\s*)output_left\s*\=\s*([^;]*).*$)|^.*$/, "$1");
    var output_width = document.cookie.replace(/(?:(?:^|.*;\s*)output_width\s*\=\s*([^;]*).*$)|^.*$/, "$1");
    var output_height = document.cookie.replace(/(?:(?:^|.*;\s*)output_height\s*\=\s*([^;]*).*$)|^.*$/, "$1");
    var output_visible = document.cookie.replace(/(?:(?:^|.*;\s*)output_visible\s*\=\s*([^;]*).*$)|^.*$/, "$1");

    var files_top = document.cookie.replace(/(?:(?:^|.*;\s*)files_top\s*\=\s*([^;]*).*$)|^.*$/, "$1");
    var files_left = document.cookie.replace(/(?:(?:^|.*;\s*)files_left\s*\=\s*([^;]*).*$)|^.*$/, "$1");
    var files_width = document.cookie.replace(/(?:(?:^|.*;\s*)files_width\s*\=\s*([^;]*).*$)|^.*$/, "$1");
    var files_height = document.cookie.replace(/(?:(?:^|.*;\s*)files_height\s*\=\s*([^;]*).*$)|^.*$/, "$1");
    var files_visible = document.cookie.replace(/(?:(?:^|.*;\s*)files_visible\s*\=\s*([^;]*).*$)|^.*$/, "$1");

    var rubric_top = document.cookie.replace(/(?:(?:^|.*;\s*)rubric_top\s*\=\s*([^;]*).*$)|^.*$/, "$1");
    var rubric_left = document.cookie.replace(/(?:(?:^|.*;\s*)rubric_left\s*\=\s*([^;]*).*$)|^.*$/, "$1");
    var rubric_width = document.cookie.replace(/(?:(?:^|.*;\s*)rubric_width\s*\=\s*([^;]*).*$)|^.*$/, "$1");
    var rubric_height = document.cookie.replace(/(?:(?:^|.*;\s*)rubric_height\s*\=\s*([^;]*).*$)|^.*$/, "$1");
    var rubric_visible = document.cookie.replace(/(?:(?:^|.*;\s*)rubric_visible\s*\=\s*([^;]*).*$)|^.*$/, "$1");

    var status_top = document.cookie.replace(/(?:(?:^|.*;\s*)status_top\s*\=\s*([^;]*).*$)|^.*$/, "$1");
    var status_left = document.cookie.replace(/(?:(?:^|.*;\s*)status_left\s*\=\s*([^;]*).*$)|^.*$/, "$1");
    var status_width = document.cookie.replace(/(?:(?:^|.*;\s*)status_width\s*\=\s*([^;]*).*$)|^.*$/, "$1");
    var status_height = document.cookie.replace(/(?:(?:^|.*;\s*)status_height\s*\=\s*([^;]*).*$)|^.*$/, "$1");
    var status_visible = document.cookie.replace(/(?:(?:^|.*;\s*)status_visible\s*\=\s*([^;]*).*$)|^.*$/, "$1");

    var regrade_top = document.cookie.replace(/(?:(?:^|.*;\s*)regrade_top\s*\=\s*([^;]*).*$)|^.*$/, "$1");
    var regrade_left = document.cookie.replace(/(?:(?:^|.*;\s*)regrade_left\s*\=\s*([^;]*).*$)|^.*$/, "$1");
    var regrade_width = document.cookie.replace(/(?:(?:^|.*;\s*)regrade_width\s*\=\s*([^;]*).*$)|^.*$/, "$1");
    var regrade_height = document.cookie.replace(/(?:(?:^|.*;\s*)regrade_height\s*\=\s*([^;]*).*$)|^.*$/, "$1");
    var regrade_visible = document.cookie.replace(/(?:(?:^|.*;\s*)regrade_visible\s*\=\s*([^;]*).*$)|^.*$/, "$1");

    var bar_wrapper_top = document.cookie.replace(/(?:(?:^|.*;\s*)bar_wrapper_top\s*\=\s*([^;]*).*$)|^.*$/, "$1");
    var bar_wrapper_left = document.cookie.replace(/(?:(?:^|.*;\s*)bar_wrapper_left\s*\=\s*([^;]*).*$)|^.*$/, "$1");
    var bar_wrapper_visible = document.cookie.replace(/(?:(?:^|.*;\s*)bar_wrapper_visible\s*\=\s*([^;]*).*$)|^.*$/, "$1");

    var overwrite = document.cookie.replace(/(?:(?:^|.*;\s*)overwrite\s*\=\s*([^;]*).*$)|^.*$/, "$1");

    var autoscroll = document.cookie.replace(/(?:(?:^|.*;\s*)autoscroll\s*\=\s*([^;]*).*$)|^.*$/, "$1");
    var opened_mark = document.cookie.replace(/(?:(?:^|.*;\s*)opened_mark\s*\=\s*([^;]*).*$)|^.*$/, "$1");
    var scroll_pixel = document.cookie.replace(/(?:(?:^|.*;\s*)scroll_pixel\s*\=\s*([^;]*).*$)|^.*$/, "$1");

    var testcases = document.cookie.replace(/(?:(?:^|.*;\s*)testcases\s*\=\s*([^;]*).*$)|^.*$/, "$1");

    var files = document.cookie.replace(/(?:(?:^|.*;\s*)files\s*\=\s*([^;]*).*$)|^.*$/, "$1");
    (output_top) ? $("#autograding_results").css("top", output_top):{};
    (output_left) ? $("#autograding_results").css("left", output_left):{};
    (output_width) ? $("#autograding_results").css("width", output_width):{};
    (output_height) ? $("#autograding_results").css("height", output_height):{};
    (output_visible) ? $("#autograding_results").css("display", output_visible):{};

    (rubric_top) ? $("#grading_rubric").css("top", rubric_top):{};
    (rubric_left) ? $("#grading_rubric").css("left", rubric_left):{};
    (rubric_width) ? $("#grading_rubric").css("width", rubric_width):{};
    (rubric_height) ? $("#grading_rubric").css("height", rubric_height):{};
    (rubric_visible) ? $("#grading_rubric").css("display", rubric_visible):{};

    (files_top) ? $("#submission_browser").css("top", files_top):{};
    (files_left) ? $("#submission_browser").css("left", files_left):{};
    (files_width) ? $("#submission_browser").css("width", files_width):{};
    (files_height) ? $("#submission_browser").css("height", files_height):{};
    (files_visible) ? $("#submission_browser").css("display", files_visible):{};

    (status_top) ? $("#student_info").css("top", status_top):{};
    (status_left) ? $("#student_info").css("left", status_left):{};
    (status_width) ? $("#student_info").css("width", status_width):{};
    (status_height) ? $("#student_info").css("height", status_height):{};
    (status_visible) ? $("#student_info").css("display", status_visible):{};

    (regrade_top) ? $("#regrade_info").css("top", regrade_top):{};
    (regrade_left) ? $("#regrade_info").css("left", regrade_left):{};
    (regrade_width) ? $("#regrade_info").css("width", regrade_width):{};
    (regrade_height) ? $("#regrade_info").css("height", regrade_height):{};
    (regrade_visible) ? $("#regrade_info").css("display", regrade_visible):{};

    (bar_wrapper_top) ? $("#bar_wrapper").css("top", bar_wrapper_top):{};
    (bar_wrapper_left) ? $("#bar_wrapper").css("left", bar_wrapper_left):{};
    (bar_wrapper_visible) ? $("#bar_wrapper").css("display", bar_wrapper_visible):{};

    (output_visible) ? ((output_visible) == "none" ? $(".fa-list-alt").removeClass("icon-selected") : $(".fa-list-alt").addClass("icon-selected")) : {};
    (files_visible) ? ((files_visible) == "none" ? $(".fa-folder-open").removeClass("icon-selected") : $(".fa-folder-open").addClass("icon-selected")) : {};
    (rubric_visible) ? ((rubric_visible) == "none" ? $(".fa-pencil-square-o").removeClass("icon-selected") : $(".fa-pencil-square-o").addClass("icon-selected")) : {};
    (status_visible) ? ((status_visible) == "none" ? $(".fa-user").removeClass("icon-selected") : $(".fa-user").addClass("icon-selected")) : {};
    (regrade_visible) ? ((regrade_visible) == "none" ? $(".fa-hand-paper-o").removeClass("icon-selected") : $(".fa-hand-paper-o").addClass("icon-selected")) : {};
   
    (overwrite) ? ((overwrite) == "on" ? $('#overwrite-id').prop('checked', true) : $('#overwrite-id').prop('checked', false)) : {};
    (autoscroll) ? ((autoscroll) == "on" ? $('#autoscroll_id').prop('checked', true) : $('#autoscroll_id').prop('checked', false)) : {};

    onAjaxInit = function() {
        $('#title-'+opened_mark).click();
        if (scroll_pixel > 0) {
            document.getElementById('grading_rubric').scrollTop = scroll_pixel;
        }
    }

    if (autoscroll == "on") {
        var files_array = JSON.parse(files);
        files_array.forEach(function(element) {
            var file_path = element.split('#$SPLIT#$');
            var current = $('#file-container');
            for (var x = 0; x < file_path.length; x++) {
                current.children().each(function() {
                    if (x == file_path.length - 1) {
                        $(this).children('div[id^=file_viewer_]').each(function() {
                            if ($(this)[0].dataset.file_name == file_path[x] && !$($(this)[0]).hasClass('open')) {
                                openFrame($(this)[0].dataset.file_name, $(this)[0].dataset.file_url, $(this).attr('id').split('_')[2]);
                            }
                        });
                        $(this).children('div[id^=div_viewer_]').each(function() {
                            if ($(this)[0].dataset.file_name == file_path[x] && !$($(this)[0]).hasClass('open')) {
                                openDiv($(this).attr('id').split('_')[2]);
                            }
                        });
                    } else {
                        $(this).children('div[id^=div_viewer_]').each(function() {
                            if ($(this)[0].dataset.file_name == file_path[x]) {
                                current = $(this);
                                return false;
                            }
                        });
                    }
                });
            }
        });
    }
    for(var x=0; x<testcases.length; x++){
        if(testcases[x]!='[' && testcases[x]!=']')
            openAutoGrading(testcases[x]);
    }
}

function updateCookies(){
    document.cookie = "output_top=" + $("#autograding_results").css("top") + "; path=/;";
    document.cookie = "output_left=" + $("#autograding_results").css("left") + "; path=/;";
    document.cookie = "output_width=" + $("#autograding_results").css("width") + "; path=/;";
    document.cookie = "output_height=" + $("#autograding_results").css("height") + "; path=/;";
    document.cookie = "output_visible=" + $("#autograding_results").css("display") + "; path=/;";

    document.cookie = "rubric_top=" + $("#grading_rubric").css("top") + "; path=/;";
    document.cookie = "rubric_left=" + $("#grading_rubric").css("left") + "; path=/;";
    document.cookie = "rubric_width=" + $("#grading_rubric").css("width") + "; path=/;";
    document.cookie = "rubric_height=" + $("#grading_rubric").css("height") + "; path=/;";
    document.cookie = "rubric_visible=" + $("#grading_rubric").css("display") + "; path=/;";

    document.cookie = "files_top=" + $("#submission_browser").css("top") + "; path=/;";
    document.cookie = "files_left=" + $("#submission_browser").css("left") + "; path=/;";
    document.cookie = "files_width=" + $("#submission_browser").css("width") + "; path=/;";
    document.cookie = "files_height=" + $("#submission_browser").css("height") + "; path=/;";
    document.cookie = "files_visible=" + $("#submission_browser").css("display") + "; path=/;";

    document.cookie = "status_top=" + $("#student_info").css("top") + "; path=/;";
    document.cookie = "status_left=" + $("#student_info").css("left") + "; path=/;";
    document.cookie = "status_width=" + $("#student_info").css("width") + "; path=/;";
    document.cookie = "status_height=" + $("#student_info").css("height") + "; path=/;";
    document.cookie = "status_visible=" + $("#student_info").css("display") + "; path=/;";

    document.cookie = "regrade_top=" + $("#regrade_info").css("top") + "; path=/;";
    document.cookie = "regrade_left=" + $("#regrade_info").css("left") + "; path=/;";
    document.cookie = "regrade_width=" + $("#regrade_info").css("width") + "; path=/;";
    document.cookie = "regrade_height=" + $("#regrade_info").css("height") + "; path=/;";
    document.cookie = "regrade_visible=" + $("#regrade_info").css("display") + "; path=/;";

    document.cookie = "bar_wrapper_top=" + $("#bar_wrapper").css("top") + "; path=/;";
    document.cookie = "bar_wrapper_left=" + $("#bar_wrapper").css("left") + "; path=/;";
    document.cookie = "bar_wrapper_visible=" + $("#bar_wrapper").css("display") + "; path=/;";
    document.cookie = "editMode=" + editModeEnabled + "; path=/;";
    var overwrite = "on";
    if ($('#overwrite-id').is(":checked")) {
        overwrite = "on";
    } else {
        overwrite = "off";
    }
    document.cookie = "overwrite=" + overwrite + "; path=/;";

    var autoscroll = "on";
    if ($('#autoscroll_id').is(":checked")) {
        autoscroll = "on";
    } else {
        autoscroll = "off";
    }
    document.cookie = "autoscroll=" + autoscroll + "; path=/;";
    if(getGradeable()!=null){
        document.cookie = "opened_mark=" + findCurrentOpenedMark() + "; path=/;";
        var testcases = findOpenTestcases();
        testcases = JSON.stringify(testcases);
        document.cookie = "testcases=" + testcases + "; path=/;";
    }
    if (findCurrentOpenedMark() > 0 || findCurrentOpenedMark() === GENERAL_MESSAGE_ID) {
        if (findCurrentOpenedMark() === GENERAL_MESSAGE_ID) {
            var current_mark = document.getElementById('title-general');
        } else {
            var current_mark = document.getElementById('title-' + findCurrentOpenedMark());
        }
        var top_pos = current_mark.offsetTop;
        var rubric_box = document.getElementById('grading-box');
        top_pos += rubric_box.offsetTop;
        document.cookie = "scroll_pixel=" + top_pos + "; path=/;";
    } else {
        document.cookie = "scroll_pixel=" + 0 + "; path=/;";
    }
    var files = [];
    $('#file-container').children().each(function() {
        $(this).children('div[id^=div_viewer_]').each(function() {
            files = files.concat(findAllOpenedFiles($(this), "", $(this)[0].dataset.file_name, [], true));
        });
    });
    files = JSON.stringify(files);
    document.cookie = "files=" + files + "; path=/;"

    document.cookie = "cookie_version=" + cookie_version + "; path=/;";
}

function changeEditorStyle(newStyle){
    if(newStyle === 'style_light'){
        localStorage.setItem("codeDisplayStyle", "light");
    } else {
        localStorage.setItem("codeDisplayStyle", "dark");
    }
    window.location.reload();
}

//-----------------------------------------------------------------------------
// Student navigation

function gotoPrevStudent() {
    if(getGradeable()!=null){
        saveLastOpenedMark(true, function () {
            window.location = $("#prev-student")[0].dataset.href;
        }, function () {
            if (confirm("Could not save last mark, change student anyway?")) {
                window.location = $("#prev-student")[0].dataset.href;
            }
        });
    }
    else{
        window.location = $("#prev-student")[0].dataset.href; 
    }
}

function gotoNextStudent() {
    if(getGradeable()!=null){
        saveLastOpenedMark(true, function () {
            window.location = $("#next-student")[0].dataset.href;
        }, function () {
            if (confirm("Could not save last mark, change student anyway?")) {
                window.location = $("#next-student")[0].dataset.href;
            }
        });
    }
    else{
       window.location = $("#next-student")[0].dataset.href; 
    }
}
//Navigate to the prev / next student buttons
registerKeyHandler({name: "Previous Student", code: "ArrowLeft"}, function() {
    gotoPrevStudent();
});
registerKeyHandler({name: "Next Student", code: "ArrowRight"}, function() {
    gotoNextStudent();
});

//-----------------------------------------------------------------------------
// Panel show/hide

function isAutogradingVisible() {
    return $("#autograding_results").is(":visible");
}

function isRubricVisible() {
    return $("#grading_rubric").is(":visible");
}

function isSubmissionsVisible() {
    return $("#submission_browser").is(":visible");
}

function isInfoVisible() {
    return $("#student_info").is(":visible");
}
function isRegradeVisible(){
    return $("#regrade_info").is(":visible");
}

function setAutogradingVisible(visible) {
    $('.fa-list-alt').toggleClass('icon-selected', visible);
    $("#autograding_results").toggle(visible);
    hideIfEmpty("#autograding_results");
}

function setRubricVisible(visible) {
    $('.fa-pencil-square-o').toggleClass('icon-selected', visible);
    $("#grading_rubric").toggle(visible);
}

function setSubmissionsVisible(visible) {
    $('.fa-folder-open.icon-header').toggleClass('icon-selected', visible);
    $("#submission_browser").toggle(visible);
    hideIfEmpty("#submission_browser");
}

function setInfoVisible(visible) {
    $('.fa-user').toggleClass('icon-selected', visible);
    $("#student_info").toggle(visible);
    hideIfEmpty("#student_info");
}

function setRegradeVisible(visible) {
    $('.fa-hand-paper-o').toggleClass('icon-selected', visible);
    $("#regrade_info").toggle(visible);
    hideIfEmpty("#regrade_info");
}

function toggleAutograding() {
    setAutogradingVisible(!isAutogradingVisible());
}

function toggleRubric() {
    setRubricVisible(!isRubricVisible());
}

function toggleSubmissions() {
    setSubmissionsVisible(!isSubmissionsVisible());
}

function toggleInfo() {
    setInfoVisible(!isInfoVisible());
}
function toggleRegrade() {
    setRegradeVisible(!isRegradeVisible());
}

function resetModules() {
    $('.fa-list-alt').addClass('icon-selected');
    $("#autograding_results").attr("style", "z-index:30; left:15px; top:175px; width:48%; height:37%; display:block;");
    $('.fa-pencil-square-o').addClass('icon-selected');
    $("#grading_rubric").attr("style", "right:15px; z-index:30; top:175px; width:48%; height:37%; display:block;");
    $('.fa-folder-open').addClass('icon-selected');
    $("#submission_browser").attr("style", "left:15px; z-index:30; bottom:40px; width:48%; height:30%; display:block;");
    $('.fa-user').addClass('icon-selected');
    $('#bar_wrapper').attr("style", "top: -90px;left: 45%; z-index:40;");
    $("#student_info").attr("style", "right:15px; bottom:40px; z-index:30; width:48%; height:30%; display:block;");
    $('.fa-hand-paper-o').addClass('icon-selected');
    $("#regrade_info").attr("style", "bottom:30px; z-index:30; right:15px; width:48%; height:37%; display:block;");
    deleteCookies();
    updateCookies();
}


registerKeyHandler({name: "Reset Panel Positions", code: "KeyR"}, function() {
    resetModules();
    updateCookies();
});
registerKeyHandler({name: "Toggle Autograding Panel", code: "KeyA"}, function() {
    toggleAutograding();
    updateCookies();
});
registerKeyHandler({name: "Toggle Rubric Panel", code: "KeyG"}, function() {
    toggleRubric();
    updateCookies();
});
registerKeyHandler({name: "Toggle Submissions Panel", code: "KeyO"}, function() {
    toggleSubmissions();
    updateCookies();
});
registerKeyHandler({name: "Toggle Student Information Panel", code: "KeyS"}, function() {
    toggleInfo();
    updateCookies();
});
registerKeyHandler({name: "Toggle Regrade Requests Panel", code: "KeyX"}, function() {
    toggleRegrade();
    updateCookies();
});
//-----------------------------------------------------------------------------
// Show/hide components

registerKeyHandler({name: "Open Next Component", code: 'ArrowDown'}, function(e) {
    var current = findCurrentOpenedMark();
    var numQuestions = getGradeable().components.length;
    if (current === NO_COMPONENT_ID) {
        openMark(1);
        $('#title-' + 1)[0].scrollIntoView();
    } else if (current === numQuestions) {
        openGeneralMessage();
        $('#title-general')[0].scrollIntoView();
    } else if (current === GENERAL_MESSAGE_ID) {
        closeGeneralMessage(true);
    } else {
        openMark(current + 1);
        $('#title-' + (current + 1))[0].scrollIntoView();
    }
    e.preventDefault();
});

registerKeyHandler({name: "Open Previous Component", code: 'ArrowUp'}, function(e) {
    var current = findCurrentOpenedMark();
    var numQuestions = getGradeable().components.length;
    if (current === NO_COMPONENT_ID) {
        openGeneralMessage();
        $('#title-general')[0].scrollIntoView();
    } else if (current === 1) {
        closeMark(1);
    } else if (current === GENERAL_MESSAGE_ID) {
        openMark(numQuestions);
        $('#title-' + numQuestions)[0].scrollIntoView();
    } else {
        openMark(current - 1);
        $('#title-' + (current - 1))[0].scrollIntoView();
    }
    e.preventDefault();
});

//-----------------------------------------------------------------------------
// Selecting marks

registerKeyHandler({name: "Select Mark 1", code: 'Digit1', locked: true}, function() {
    selectCurrentMarkCheck(0);
});
registerKeyHandler({name: "Select Mark 2", code: 'Digit2', locked: true}, function() {
    selectCurrentMarkCheck(1);
});
registerKeyHandler({name: "Select Mark 3", code: 'Digit3', locked: true}, function() {
    selectCurrentMarkCheck(2);
});
registerKeyHandler({name: "Select Mark 4", code: 'Digit4', locked: true}, function() {
    selectCurrentMarkCheck(3);
});
registerKeyHandler({name: "Select Mark 5", code: 'Digit5', locked: true}, function() {
    selectCurrentMarkCheck(4);
});
registerKeyHandler({name: "Select Mark 6", code: 'Digit6', locked: true}, function() {
    selectCurrentMarkCheck(5);
});
registerKeyHandler({name: "Select Mark 7", code: 'Digit7', locked: true}, function() {
    selectCurrentMarkCheck(6);
});
registerKeyHandler({name: "Select Mark 8", code: 'Digit8', locked: true}, function() {
    selectCurrentMarkCheck(7);
});
registerKeyHandler({name: "Select Mark 9", code: 'Digit9', locked: true}, function() {
    selectCurrentMarkCheck(8);
});

function selectCurrentMarkCheck(index) {
    var opened = findCurrentOpenedMark();
    if (opened > 0 && index < getComponent(opened).marks.length) {
        var mark = getComponent(opened).marks[index];
        selectMark($("#mark_id-" + opened + "-" + mark.id + "-check")[0]);
    }
}


// expand all files in Submissions and Results section
function openAll(click_class, class_modifier) {
    $("."+click_class + class_modifier).each(function(){
        $(this).click();
    });
}
function updateValue(obj, option1, option2) {
    // Switches the value of an element between option 1 and two
    console.log('hi');
    obj.text(function(i, oldText){
        if(oldText.indexOf(option1) >= 0){
            newText = oldText.replace(option1, option2);
        } else{
            newText = oldText.replace(option2, option1);
        }
        return newText;
    });

}
function openAutoGrading(num){
    $('#tc_' + num).click();
    $('#testcase_' + num)[0].style.display="block";
}
// expand all outputs in Auto-Grading Testcases section
function openAllAutoGrading() {
    // show all divs whose id starts with testcase_
     $("[id^='tc_']").click();
     $("[id^='testcase_']")[0].style.display="block";
}

// close all outputs in Auto-Grading Testcases section
function closeAllAutoGrading() {
    // hide all divs whose id starts with testcase_
    $("[id^='testcase_']").hide();
}


function openDiv(num) {
    var elem = $('#div_viewer_' + num);
    if (elem.hasClass('open')) {
        elem.hide();
        elem.removeClass('open');
        $($($(elem.parent().children()[0]).children()[0]).children()[0]).removeClass('fa-folder-open').addClass('fa-folder');
    }
    else {
        elem.show();
        elem.addClass('open');
        $($($(elem.parent().children()[0]).children()[0]).children()[0]).removeClass('fa-folder').addClass('fa-folder-open');
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

// delta in this function is the incremental step of points, currently hardcoded to 0.5pts
function validateInput(id, question_total, delta){
    var ele = $('#' + id);
    if(isNaN(parseFloat(ele.val())) || ele.val() == ""){
        ele.val("");
        return;
    }
    if(ele.val() < 0 && parseFloat(question_total) > 0) {
        ele.val( 0 );
    }
    if(ele.val() > 0 && parseFloat(question_total) < 0) {
        ele.val( 0 );
    }
    if(ele.val() < parseFloat(question_total) && parseFloat(question_total) < 0) {
        ele.val(question_total);
    }
    if(ele.val() > parseFloat(question_total) && parseFloat(question_total) > 0) {
        ele.val(question_total);
    }
    if(ele.val() % delta != 0) {
        ele.val( Math.round(ele.val() / delta) * delta );
    }
}

// autoresize the comment box
function autoResizeComment(e){
    e.target.style.height ="";
    e.target.style.height = e.target.scrollHeight + "px";
}

function downloadFile(html_file, url_file) {
    var directory = "";
    if (url_file.includes("submissions")) {
        directory = "submissions";
    }
    else if (url_file.includes("results")) {
        directory = "results";
    }
    window.location = buildUrl({'component': 'misc', 'page': 'download_file', 'dir': directory, 'file': html_file, 'path': url_file});
    return false;
}

function hideIfEmpty(element) {
    $(element).each(function() {
        if ($(this).hasClass("empty")) {
            $(this).hide();
        }
    });
}

function findOpenTestcases() {
    var testcase_num = [];
    var current_testcase;
    $(".box").each(function() {
        current_testcase = $(this).find('div[id^=testcase_]');
        if (typeof current_testcase[0] !== 'undefined'){
            if (current_testcase[0].style.display != 'none' ) {
                testcase_num.push(parseInt(current_testcase.attr('id').split('_')[1]));
            }
        }
    });
    return testcase_num;
}

//finds all the open files and folder and stores them in stored_paths
function findAllOpenedFiles(elem, current_path, path, stored_paths, first) {
    if (first === true) {
        current_path += path;
        if ($(elem)[0].classList.contains('open')) {
            stored_paths.push(path);
        }
        else {
            return [];
        }
    } else {
        current_path += "#$SPLIT#$" + path;
    }

    $(elem).children().each(function() {
        $(this).children('div[id^=file_viewer_]').each(function() {
            if ($(this)[0].classList.contains('shown')) {
                stored_paths.push((current_path + "#$SPLIT#$" + $(this)[0].dataset.file_name));
            }
        });

    });

    $(elem).children().each(function() {
        $(this).children('div[id^=div_viewer_]').each(function() {
            if ($(this)[0].classList.contains('open')) {
                stored_paths.push((current_path + "#$SPLIT#$" + $(this)[0].dataset.file_name));
                stored_paths = findAllOpenedFiles($(this), current_path, $(this)[0].dataset.file_name, stored_paths, false);
            }
        });
    });

    return stored_paths;
}

function adjustSize(name) {
    var textarea = document.getElementById(name);
    textarea.style.height = "";
    textarea.style.height = Math.min(textarea.scrollHeight, 300) + "px";
}
//-----------------------------------------------------------------------------
// Edit Mode
function toggleEditMode(){
    if(editModeEnabled==null){
        editModeEnabled=false;
    }
    editModeEnabled=!editModeEnabled;
    if(findCurrentOpenedMark()>0){
        if(editModeEnabled==false){
            $('#marks-extra-'+findCurrentOpenedMark())[0].style.display="none";
        }
        if(editModeEnabled==true){
            $('#marks-extra-'+findCurrentOpenedMark())[0].style.display="block";
        }
        var id=findCurrentOpenedMark();
        saveMark(id, true);
        updateMarksOnPage(id);
    }
}