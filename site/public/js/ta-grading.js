//Used to reset users cookies
let cookie_version = 1;

// Width of mobile and Tablet screens width
const MOBILE_WIDTH = 540;

// tracks if the current display screen is mobile
let isMobileView = false;

// Panel elements info to be used for layout designs
let panelElements = [
  { str: "autograding_results", icon: ".grading_toolbar .fa-list"},
  { str: "grading_rubric", icon: ".grading_toolbar .fa-edit"},
  { str: "submission_browser", icon: "grading_toolbar .fa-folder-open.icon-header"},
  { str: "solution_ta_notes", icon: "grading_toolbar .fa-check.icon-header"},
  { str: "student_info", icon: ".grading_toolbar .fa-user"},
  { str: "peer_info", icon: ".grading_toolbar .fa-users"},
  { str: "discussion_browser", icon: ".grading_toolbar .fa-comment-alt"},
  { str: "regrade_info", icon: ".grading_toolbar .grade_inquiry_icon"},
  { str: "notebook-view", icon: ".grading_toolbar .fas fa-book-open"}
];

// Tracks the layout of TA grading page
const taLayoutDet = {
  numOfPanelsEnabled: 1,
  isFullScreenMode: false,
  isFullLeftColumnMode: false,
  currentOpenPanel: panelElements[0].str,
  currentTwoPanels: {
    leftTop: null,
    leftBottom: null,
    rightTop: null,
    rightBottom: null,
  },
  dividedColName: "LEFT",
  leftPanelWidth: "50%",
  bottomPanelHeight: "50%",
  bottomFourPanelRightHeight: "50%",
};

// Grading Panel header width
let maxHeaderWidth = 0;
// Navigation Toolbar Panel header width
let maxNavbarWidth = 0;

// Various Ta-grading page selector for DOM manipulation
let panelsContSelector = ".two-panel-cont";
const leftSelector = ".two-panel-item.two-panel-left";
const verticalDragBarSelector = ".two-panel-drag-bar";
const leftHorizDragBarSelector = ".panel-item-section-drag-bar.panel-item-left-drag";
const rightHorizDragBarSelector = ".panel-item-section-drag-bar.panel-item-right-drag";
const panelsBucket = {
  leftTopSelector : ".two-panel-item.two-panel-left .left-top",
  leftBottomSelector : ".two-panel-item.two-panel-left .left-bottom",
  rightTopSelector : ".two-panel-item.two-panel-right .right-top",
  rightBottomSelector : ".two-panel-item.two-panel-right .right-bottom",
};

// Only keep those panels which are available
function updateThePanelsElements(panelsAvailabilityObj) {
  // Attach the isAvailable to the panel elements to manage them
  panelElements = panelElements.filter((panel) => {
    return !!panelsAvailabilityObj[panel.str];
  });
}

$(function () {
  Object.assign(taLayoutDet, getSavedTaLayoutDetails());
  // Check initially if its the mobile screen view or not
  isMobileView = window.innerWidth <= MOBILE_WIDTH;
  initializeTaLayout();

  window.addEventListener('resize', () => {
    let wasMobileView = isMobileView;
    isMobileView = window.innerWidth <= MOBILE_WIDTH;
    // if the width is switched between smaller and bigger screens, re-initialize the layout
    if (wasMobileView !== isMobileView) {
      initializeTaLayout();
    }
  });

  loadTAGradingSettingData();

  $('#settings-popup').on('change', '.ta-grading-setting-option', function() {
    var storageCode = $(this).attr('data-storage-code');
    if(storageCode) {
      localStorage.setItem(storageCode, this.value);
    }
  })

  // Progress bar value
  let value = $(".progressbar").val() ? $(".progressbar").val() : 0;
  $(".progress-value").html("<b>" + value + '%</b>');

  // Grading panel toggle buttons
  $(".grade-panel button").click(function () {
    const btnCont = $(this).parent();
    let panelSpanId = btnCont.attr('id');

    if (!panelSpanId) {
      return;
    }

    const panelId = panelSpanId.split(/(_|-)btn/)[0];
    const selectEle =  $('select#' + panelId + '_select');

    // Hide all select dropdown except the current one
    $('select.panel-position-cont').not(selectEle).hide();

    const isPanelOpen = $('#' + panelId).is(':visible');
    // If panel is not in-view and two/three-panel-mode is enabled show the drop-down to select position,
    // otherwise just toggle it
    if (isPanelOpen || +taLayoutDet.numOfPanelsEnabled === 1) {
      setPanelsVisibilities(panelId);
    } else {
      // removing previously selected option
      selectEle.val(0);
      selectEle.is(':visible') ? selectEle.hide() : selectEle.show();
    }
  });

  // panel position selector change event
  $(".grade-panel .panel-position-cont").change(function() {
    let panelSpanId = $(this).parent().attr('id');
    let position = $(this).val();
    if (panelSpanId) {
      const panelId = panelSpanId.split(/(_|-)btn/)[0];
      setPanelsVisibilities(panelId, null, position);
      $('select#' + panelId + '_select').hide();
      checkNotebookScroll();
    }
  });
  notebookScrollLoad();

  checkNotebookScroll();

  if(localStorage.getItem('notebook-setting-file-submission-expand') == 'true') {
    let notebookPanel = $('#notebook-view');
    if(notebookPanel.length != 0) {
      let notebookItems = notebookPanel.find('.openAllFilesubmissions');
      for(var i = 0; i < notebookItems.length; i++) {
        notebookItems[i].onclick();
      }
    }
  }


  // Remove the select options which are open
  function hidePanelPositionSelect() {
    $('select.panel-position-cont').hide();
    document.removeEventListener('click', hidePanelPositionSelect);
  }
  // Check for the panels status initially
  adjustGradingPanelHeader();
  const resizeObserver = new ResizeObserver(() => {
      adjustGradingPanelHeader();
  });
  // calling it for the first time i.e initializing
  adjustGradingPanelHeader();
  resizeObserver.observe(document.getElementById('grading-panel-header'));

  // Dynamically resize the textarea height as per the provided content
  document.querySelectorAll('[id^=textbox-solution-]').forEach( textarea => {
    textarea.addEventListener('keyup', function () {
      setTimeout(function() {
        textarea.style.height = 'auto';
        textarea.style.height = textarea.scrollHeight + 'px';
      },0);
    });
  });

});

function checkNotebookScroll() {
  if (taLayoutDet.currentTwoPanels.leftTop === 'notebook-view'
    || taLayoutDet.currentTwoPanels.leftBottom === 'notebook-view'
    || taLayoutDet.currentTwoPanels.rightTop === 'notebook-view'
    || taLayoutDet.currentTwoPanels.rightBottom === 'notebook-view'
    || taLayoutDet.currentOpenPanel === 'notebook-view'
  ) {
    $('#notebook-view').scroll(delayedNotebookSave());
  } else {
    $('#notebook-view').off('scroll');
    localStorage.removeItem('ta-grading-notebook-view-scroll-id');
    localStorage.removeItem('ta-grading-notebook-view-scroll-item');
  }
}

function delayedNotebookSave() {
  var timer;
  return function() {
    timer && clearTimeout(timer);
    timer = setTimeout(notebookScrollSave, 250);
  }
}

function notebookScrollLoad() {
  var notebookView = $('#notebook-view');
  if (notebookView !== 0 && notebookView.is(":visible")) {
    var elementID = localStorage.getItem('ta-grading-notebook-view-scroll-id');
    var element = null;
    if (elementID === null) {
      elementID = localStorage.getItem('ta-grading-notebook-view-scroll-item');
      if (elementID !== null) {
        element = $('[data-item-ref=' + elementID + ']');
      }
    } else {
      element = $('[data-non-item-ref=' + elementID + ']');
    }
    if (element !== null) {
      if (element.length !== 0) {
        notebookView.scrollTop(element.offset().top - notebookView.offset().top + notebookView.scrollTop());
      } else {
        localStorage.removeItem('ta-grading-notebook-view-scroll-id');
        localStorage.removeItem('ta-grading-notebook-view-scroll-item');
      }
    }
  }
}

function notebookScrollSave() {
  var notebookView = $('#notebook-view');
  if (notebookView.length !== 0 && notebookView.is(':visible')) {
    var notebookTop = $('#notebook-view').offset().top;
    var element = $('#content_0');
    if(notebookView.scrollTop() + notebookView.innerHeight() + 1 > notebookView[0].scrollHeight) {
      element = $('[id^=content_').last();
    } else {
      while (element.length !== 0) {
        if (element.offset().top > notebookTop) {
          break;
        }
        element = element.next();
      }
    }

    if (element.length !== 0) {
      if (element.attr('data-item-ref') === undefined) {
        localStorage.setItem('ta-grading-notebook-view-scroll-id', element.attr('data-non-item-ref'));
        localStorage.removeItem('ta-grading-notebook-view-scroll-item');
      } else {
        localStorage.setItem('ta-grading-notebook-view-scroll-item', element.attr('data-item-ref'));
        localStorage.removeItem('ta-grading-notebook-view-scroll-id');
      }
    }
  }
}

// returns taLayoutDet object from LS, and if its not present returns empty object
function getSavedTaLayoutDetails() {
  const savedData = localStorage.getItem('taLayoutDetails');
  return savedData ? JSON.parse(savedData) : {};
}

function saveTaLayoutDetails() {
  localStorage.setItem("taLayoutDetails", JSON.stringify(taLayoutDet));
}

function saveResizedColsDimensions(updateValue, isHorizontalResize) {
  if (isHorizontalResize) {
    taLayoutDet.bottomPanelHeight = updateValue;
  }
  else {
    taLayoutDet.leftPanelWidth = updateValue;
  }
  saveTaLayoutDetails();
}

function saveRightResizedColsDimensions(updateValue, isHorizontalResize) {
  if (isHorizontalResize) {
    taLayoutDet.bottomFourPanelRightHeight = updateValue;
  }
  else {
    taLayoutDet.leftPanelWidth = updateValue;
  }
  saveTaLayoutDetails();
}

function initializeHorizontalTwoPanelDrag () {
  if (taLayoutDet.dividedColName === "RIGHT") {
    initializeResizablePanels(panelsBucket.rightBottomSelector, rightHorizDragBarSelector, true, saveResizedColsDimensions)
  }
  if (taLayoutDet.dividedColName === "LEFT") {
    if(taLayoutDet.numOfPanelsEnabled === 4) {
      initializeResizablePanels(panelsBucket.rightBottomSelector, rightHorizDragBarSelector, true, saveRightResizedColsDimensions);
    }
    initializeResizablePanels(panelsBucket.leftBottomSelector, leftHorizDragBarSelector, true, saveResizedColsDimensions);
  }
}

function initializeTaLayout() {
  if (isMobileView) {
    resetSinglePanelLayout();
  }
  else if (taLayoutDet.numOfPanelsEnabled) {
    togglePanelLayoutModes(true);
    if (taLayoutDet.isFullLeftColumnMode) {
      toggleFullLeftColumnMode(true);
    }
    // initialize the layout\
    initializeResizablePanels(leftSelector, verticalDragBarSelector, false, saveResizedColsDimensions);
    initializeHorizontalTwoPanelDrag();
  }
  else {
    setPanelsVisibilities(taLayoutDet.currentOpenPanel);
  }
  if (taLayoutDet.isFullScreenMode) {
    toggleFullScreenMode();
  }
  updateLayoutDimensions();
  updatePanelOptions();
  readCookies();
}

function updateLayoutDimensions() {
  // updates width of left columns (normal + full-left-col) with the last saved layout width
  $(".two-panel-item.two-panel-left").css({
    width: taLayoutDet.leftPanelWidth ? taLayoutDet.leftPanelWidth : "50%"
  });
  // updates width of left columns (normal + full-left-col) with the last saved layout width
  const bottomRow = taLayoutDet.dividedColName === "RIGHT" ? $(".panel-item-section.right-bottom") : $(".panel-item-section.left-bottom");
  bottomRow.css({
    height: taLayoutDet.bottomPanelHeight ? taLayoutDet.bottomPanelHeight : "50%"
  });

  if (taLayoutDet.numOfPanelsEnabled === 4) {
    $(".panel-item-section.right-bottom").css({
      height: taLayoutDet.bottomFourPanelRightHeight ? taLayoutDet.bottomFourPanelRightHeight : "50%"
    });
  }
}

function updatePanelOptions() {
  if (taLayoutDet.numOfPanelsEnabled === 1) {
    return;
  }
  $(".grade-panel .panel-position-cont").attr("size", taLayoutDet.numOfPanelsEnabled);
  const panelOptions = $(".grade-panel .panel-position-cont option");
  panelOptions.each(idx => {
    if (panelOptions[idx].value === "leftTop") {
      if (taLayoutDet.numOfPanelsEnabled === 2 || (taLayoutDet.numOfPanelsEnabled === 3 && taLayoutDet.dividedColName === "RIGHT")) {
        panelOptions[idx].text = "Open as left panel";
      }
      else {
        panelOptions[idx].text = "Open as top left panel";
      }
    }
    else if (panelOptions[idx].value === "leftBottom") {
      if (taLayoutDet.numOfPanelsEnabled === 2 || (taLayoutDet.numOfPanelsEnabled === 3 && taLayoutDet.dividedColName === "RIGHT")) {
        panelOptions[idx].classList.add("hide");
      }
      else {
        panelOptions[idx].classList.remove("hide");
      }
    }
    else if (panelOptions[idx].value === "rightTop") {
      if (taLayoutDet.numOfPanelsEnabled === 2 || (taLayoutDet.numOfPanelsEnabled === 3 && taLayoutDet.dividedColName !== "RIGHT")) {
        panelOptions[idx].text = "Open as right panel";
      }
      else {
        panelOptions[idx].text = "Open as top right panel";
      }
    }
    else if (panelOptions[idx].value === "rightBottom") {
      if (taLayoutDet.numOfPanelsEnabled === 2 || (taLayoutDet.numOfPanelsEnabled === 3 && taLayoutDet.dividedColName !== "RIGHT")) {
        panelOptions[idx].classList.add("hide");
      }
      else {
        panelOptions[idx].classList.remove("hide");
      }
    }
  });
}

/*
  Adjust buttons inside Grading panel header and shows only icons on smaller screens
 */
function adjustGradingPanelHeader () {
  const header = $('#grading-panel-header');
  const headerBox = $('.panel-header-box');
  const navBar = $('#bar_wrapper');
  const navBarBox = $('.navigation-box');

  if (maxHeaderWidth < headerBox.width()) {
    maxHeaderWidth = headerBox.width();
  }
  if (maxHeaderWidth > header.width()) {
    headerBox.addClass('smaller-header');
  } else {
    headerBox.removeClass('smaller-header');
  }
  // changes for the navigation toolbar buttons
  if (maxNavbarWidth < $('.grading_toolbar').width()) {
    maxNavbarWidth = $('.grading_toolbar').width();
  }
  if (maxNavbarWidth > navBar.width()) {
    navBarBox.addClass('smaller-navbar');
  } else {
    navBarBox.removeClass('smaller-navbar');
  }
  // On mobile display screen hide the two-panel-mode
  if (isMobileView) {
    // hide the buttons
    navBarBox.addClass('mobile-view');
  } else {
    navBarBox.removeClass('mobile-view');
  }
  // From the complete content remove the height occupied by navigation-bar and panel-header element
  document.querySelector('.panels-container').style.height = "calc(100% - " + (header.outerHeight() + navBar.outerHeight()) + "px)";
}

function onAjaxInit() {}

function readCookies(){

  let silent_edit_enabled = document.cookie.replace(/(?:(?:^|.*;\s*)silent_edit_enabled\s*\=\s*([^;]*).*$)|^.*$/, "$1") === 'true';

  let autoscroll = document.cookie.replace(/(?:(?:^|.*;\s*)autoscroll\s*\=\s*([^;]*).*$)|^.*$/, "$1");
  let opened_mark = document.cookie.replace(/(?:(?:^|.*;\s*)opened_mark\s*\=\s*([^;]*).*$)|^.*$/, "$1");
  let scroll_pixel = document.cookie.replace(/(?:(?:^|.*;\s*)scroll_pixel\s*\=\s*([^;]*).*$)|^.*$/, "$1");

  let testcases = document.cookie.replace(/(?:(?:^|.*;\s*)testcases\s*\=\s*([^;]*).*$)|^.*$/, "$1");

  let files = document.cookie.replace(/(?:(?:^|.*;\s*)files\s*\=\s*([^;]*).*$)|^.*$/, "$1");

  $('#silent-edit-id').prop('checked', silent_edit_enabled);

  onAjaxInit = function() {
    $('#title-'+opened_mark).click();
    if (scroll_pixel > 0) {
      document.getElementById('grading_rubric').scrollTop = scroll_pixel;
    }
  };

  if (autoscroll == "on") {
    $('#autoscroll_id')[0].checked = true;
    let files_array = JSON.parse(files);
    files_array.forEach(function(element) {
      let file_path = element.split('#$SPLIT#$');
      let current = $('#file-container');
      for (let x = 0; x < file_path.length; x++) {
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
          }
          else {
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
  for(let x=0; x<testcases.length; x++){
    if(testcases[x]!=='[' && testcases[x]!==']')
      openAutoGrading(testcases[x]);
  }
}

function updateCookies(){
  document.cookie = "silent_edit_enabled=" + isSilentEditModeEnabled() + "; path=/;";
  let autoscroll = $('#autoscroll_id').is(":checked") ? "on" : "off";
  document.cookie = "autoscroll=" + autoscroll + "; path=/;";

  let files = [];
  $('#file-container').children().each(function() {
    $(this).children('div[id^=div_viewer_]').each(function() {
      files = files.concat(findAllOpenedFiles($(this), "", $(this)[0].dataset.file_name, [], true));
    });
  });
  files = JSON.stringify(files);
  document.cookie = "files=" + files + "; path=/;";

  document.cookie = "cookie_version=" + cookie_version + "; path=/;";
}

//-----------------------------------------------------------------------------
// Student navigation
function gotoMainPage() {

  let window_location = $("#main-page")[0].dataset.href

  if (getGradeableId() !== '') {
    closeAllComponents(true).then(function () {
      window.location = window_location;
    }).catch(function () {
      if (confirm("Could not save open component, go to main page anyway?")) {
        window.location = window_location;
      }
    });
  }
  else {
    window.location = window_location;
  }
}

function gotoPrevStudent(to_ungraded = false) {

  let selector;
  let window_location;

  if(to_ungraded === true) {
    selector = "#prev-ungraded-student";
    window_location = $(selector)[0].dataset.href;

    // Append extra get param
    window_location += '&component_id=' + getFirstOpenComponentId();

  }
  else {
    selector = "#prev-student";
    window_location = $(selector)[0].dataset.href
  }

  if (getGradeableId() !== '') {
    closeAllComponents(true).then(function () {
      window.location = window_location;
    }).catch(function () {
      if (confirm("Could not save open component, change student anyway?")) {
        window.location = window_location;
      }
    });
  }
  else {
    window.location = window_location;
  }
}

function gotoNextStudent(to_ungraded = false) {

  let selector;
  let window_location;

  if(to_ungraded === true) {
    selector = "#next-ungraded-student";
    window_location = $(selector)[0].dataset.href;

    // Append extra get param
    window_location += '&component_id=' + getFirstOpenComponentId();
  }
  else {
    selector = "#next-student";
    window_location = $(selector)[0].dataset.href
  }

  if (getGradeableId() !== '') {
    closeAllComponents(true).then(function () {
      window.location = window_location;
    }).catch(function () {
      if (confirm("Could not save open component, change student anyway?")) {
        window.location = window_location;
      }
    });
  }
  else {
    window.location = window_location;
  }
}
//Navigate to the prev / next student buttons
registerKeyHandler({name: "Previous Student", code: "ArrowLeft"}, function() {
  gotoPrevStudent();
});
registerKeyHandler({name: "Next Student", code: "ArrowRight"}, function() {
  gotoNextStudent();
});

//Navigate to the prev / next student buttons
registerKeyHandler({name: "Previous Ungraded Student", code: "Shift ArrowLeft"}, function() {
  gotoPrevStudent(true);
});
registerKeyHandler({name: "Next Ungraded Student", code: "Shift ArrowRight"}, function() {
  gotoNextStudent(true);
});

//-----------------------------------------------------------------------------
// Panel show/hide
//

function resetSinglePanelLayout() {
  // hide all the two-panel-mode related nodes
  $('.two-panel-cont').removeClass("active");
  $("#two-panel-exchange-btn").removeClass("active");

  // Remove the full-left-column view (if it's currently present or is in-view) as it's meant for two-panel-mode only
  $(".two-panel-item.two-panel-left, .two-panel-drag-bar").removeClass("active");

  // remove the left bottom sectin and its drag bar
  $(".panel-item-section.left-bottom, .panel-item-section.right-bottom, .panel-item-section-drag-bar").removeClass("active");

  const leftTopPanelId = taLayoutDet.currentTwoPanels.leftTop;
  const leftBottomPanelId = taLayoutDet.currentTwoPanels.leftBottom;
  const rightTopPanelId = taLayoutDet.currentTwoPanels.rightTop;
  const rightBottomPanelId = taLayoutDet.currentTwoPanels.rightBottom;
  //Now Fetch the panels from DOM
  const leftTopPanel = document.getElementById(leftTopPanelId);
  const leftBottomPanel = document.getElementById(leftBottomPanelId);
  const rightTopPanel = document.getElementById(rightTopPanelId);
  const rightBottomPanel = document.getElementById(rightBottomPanelId);

  if (rightBottomPanel) {
    document.querySelector('.panels-container').append(rightBottomPanel);
    taLayoutDet.currentOpenPanel = rightBottomPanelId;
  }
  if (rightTopPanel) {
    document.querySelector('.panels-container').append(rightTopPanel);
    taLayoutDet.currentOpenPanel = rightTopPanelId;
  }
  if (leftBottomPanel) {
    document.querySelector('.panels-container').append(leftBottomPanel);
    taLayoutDet.currentOpenPanel = leftBottomPanelId;
  }
  if (leftTopPanel) {
    document.querySelector('.panels-container').append(leftTopPanel);
    taLayoutDet.currentOpenPanel = leftTopPanelId;
  }
  // current open panel will be either left or right panel from two-panel-mode
  // passing forceVisible = true, otherwise this method will just toggle it and it will get hidden
  taLayoutDet.isFullLeftColumnMode = false;
  setPanelsVisibilities(taLayoutDet.currentOpenPanel, true);
}

function checkForTwoPanelLayoutChange (isPanelAdded, panelId = null, panelPosition = null) {
  // update the global variable
  if (isPanelAdded) {
    taLayoutDet.currentTwoPanels[panelPosition] = panelId;
  } else {
    // panel is going to be removed from screen
    // check which one out of the left or right is going to be hidden
    if (taLayoutDet.currentTwoPanels.leftTop === panelId ) {
      taLayoutDet.currentTwoPanels.leftTop = null;
    }
    else if (taLayoutDet.currentTwoPanels.leftBottom === panelId ) {
      taLayoutDet.currentTwoPanels.leftBottom = null;
    }
    else if (taLayoutDet.currentTwoPanels.rightTop === panelId ) {
      taLayoutDet.currentTwoPanels.rightTop = null;
    }
    else if (taLayoutDet.currentTwoPanels.rightBottom === panelId ) {
      taLayoutDet.currentTwoPanels.rightBottom = null;
    }
  }
  saveTaLayoutDetails();
}

// Keep only those panels which are part of the two panel layout
function setMultiPanelModeVisiblities () {
    $("#panel-instructions").hide();
    panelElements.forEach((panel) => {
      let id_str = document.getElementById("#" + panel.str + "_btn") ? "#" + panel.str + "_btn" : "#" + panel.str + "-btn";

      if (taLayoutDet.currentTwoPanels.leftTop === panel.str
          || taLayoutDet.currentTwoPanels.leftBottom === panel.str
          || taLayoutDet.currentTwoPanels.rightTop === panel.str
          || taLayoutDet.currentTwoPanels.rightBottom === panel.str
      ) {
        $("#" + panel.str).toggle(true);
        $(panel.icon).toggleClass('icon-selected', true);
        $(id_str).toggleClass('active', true);
      } else {
        $("#" + panel.str).toggle(false);
        $(panel.icon).toggleClass('icon-selected', false);
        $(id_str).toggleClass('active', false);
      }
    });
}

function setPanelsVisibilities (ele, forceVisible=null, position=null) {
  panelElements.forEach((panel) => {
    let id_str = document.getElementById(panel.str + "_btn") ? "#" + panel.str + "_btn" : "#" + panel.str + "-btn";
    if (panel.str === ele) {
      const eleVisibility = forceVisible !== null ? forceVisible : !$("#" + panel.str).is(":visible");
      $("#" + panel.str).toggle(eleVisibility);
      $(panel.icon).toggleClass('icon-selected', eleVisibility);
      $(id_str).toggleClass('active', eleVisibility);
      $("#" + panel.str).find(".CodeMirror").each(function() {this.CodeMirror.refresh()});

      if (taLayoutDet.numOfPanelsEnabled > 1 && !isMobileView) {
        checkForTwoPanelLayoutChange(eleVisibility, panel.str, position);
      } else {
        // update the global variable
        taLayoutDet.currentOpenPanel = eleVisibility ? panel.str : null;
      }
      if (taLayoutDet.currentOpenPanel === null) {
        $("#panel-instructions").show();
      }
      else {
        $("#panel-instructions").hide();
      }
    } else if ((taLayoutDet.numOfPanelsEnabled && !isMobileView
      && taLayoutDet.currentTwoPanels.rightTop !== panel.str
      &&  taLayoutDet.currentTwoPanels.rightBottom !== panel.str
      && taLayoutDet.currentTwoPanels.leftTop !== panel.str
      &&  taLayoutDet.currentTwoPanels.leftBottom !== panel.str) || panel.str !== ele ) {
      //only hide those panels which are not given panel and not in taLayoutDet.currentTwoPanels if the twoPanelMode is enabled
      $("#" + panel.str).hide();
      $(panel.icon).removeClass('icon-selected');
      $(id_str).removeClass('active');
    }
  });
  // update the two-panels-layout if it's enabled
  if (taLayoutDet.numOfPanelsEnabled > 1 && !isMobileView) {
    updatePanelLayoutModes();
  } else {
    saveTaLayoutDetails();
  }
}

function toggleFullScreenMode () {
  $("main#main").toggleClass('full-screen-mode');
  $("#fullscreen-btn-cont").toggleClass('active');
  taLayoutDet.isFullScreenMode = $("main#main").hasClass('full-screen-mode');

  // update the dragging event for two panels
  initializeResizablePanels(leftSelector, verticalDragBarSelector, false, saveResizedColsDimensions);
  //Save the taLayoutDetails in LS
  saveTaLayoutDetails();
}

function toggleFullLeftColumnMode (forceVal = false) {
  // toggle between the normal left and full left panel mode
  if (!forceVal) {
    taLayoutDet.isFullLeftColumnMode = !taLayoutDet.isFullLeftColumnMode;
  }

  let newPanelsContSelector = taLayoutDet.isFullLeftColumnMode ? ".content-items-container" : ".two-panel-cont";

  let leftPanelCont = document.querySelector(leftSelector);
  let dragBar = document.querySelector(verticalDragBarSelector);
  document.querySelector(newPanelsContSelector).prepend(leftPanelCont, dragBar);

  panelsContSelector = newPanelsContSelector;
}

/**
 *
 * @param panelsCount
 * @param isLeftTaller
 * @param twoOnRight
 */
function changePanelsLayout(panelsCount, isLeftTaller, twoOnRight = false) {
  taLayoutDet.numOfPanelsEnabled = +panelsCount;
  taLayoutDet.isFullLeftColumnMode = isLeftTaller;

  taLayoutDet.dividedColName = twoOnRight ? "RIGHT" : "LEFT";

  togglePanelLayoutModes(true);
  toggleFullLeftColumnMode(true);
  initializeResizablePanels(leftSelector, verticalDragBarSelector, false, saveResizedColsDimensions);
  initializeHorizontalTwoPanelDrag();
  togglePanelSelectorModal(false);
}

function togglePanelLayoutModes(forceVal = false) {
  const twoPanelCont = $('.two-panel-cont');
  if (!forceVal) {
    taLayoutDet.numOfPanelsEnabled = +taLayoutDet.numOfPanelsEnabled === 3 ? 1 : +taLayoutDet.numOfPanelsEnabled + 1;
  }
  if (taLayoutDet.currentOpenPanel === null) {
    $("#panel-instructions").show();
  }
  else {
    $("#panel-instructions").hide();
  }

  if (taLayoutDet.numOfPanelsEnabled === 2 && !isMobileView) {
    twoPanelCont.addClass("active");
    $("#two-panel-exchange-btn").addClass("active");
    $(".panel-item-section.left-bottom, .panel-item-section.right-bottom, .panel-item-section-drag-bar").removeClass("active");
    $(".two-panel-item.two-panel-left, .two-panel-drag-bar").addClass("active");
    // If there is any panel opened just use that and fetch the next one for left side...
    if (taLayoutDet.currentOpenPanel && !(taLayoutDet.currentTwoPanels.leftTop || taLayoutDet.currentOpenPanel.rightTop)) {
      taLayoutDet.currentTwoPanels.leftTop = taLayoutDet.currentOpenPanel;
      panelElements.every((panel, idx) => {
        if (taLayoutDet.currentTwoPanels.leftTop === panel.str) {
          let nextIdx = (idx + 1) === panelElements.length ? 0 : idx + 1;
          taLayoutDet.currentTwoPanels.rightTop = panelElements[nextIdx].str;
          return false;
        }
        return true;
      });

    } else if(!taLayoutDet.currentOpenPanel) {
      // if there is no currently opened panel fill the panels with the first two
      taLayoutDet.currentTwoPanels = {
        leftTop: panelElements[0].str,
        leftBottom: null,
        rightTop: panelElements[1].str,
        rightBottom: null,
      };
    }
    updatePanelLayoutModes();
  }
  else if (+taLayoutDet.numOfPanelsEnabled === 3 && !isMobileView) {
    twoPanelCont.addClass("active");
    $(".two-panel-item.two-panel-left, .two-panel-drag-bar").addClass("active");
    let topPanel = taLayoutDet.currentTwoPanels.leftTop;
    let bottomPanel = taLayoutDet.currentTwoPanels.leftBottom;

    // If currentOpenPanels does not contain selector for leftBottom, calculate which panel to open
    let prevPanel = topPanel ? topPanel : taLayoutDet.currentTwoPanels.rightTop;

    if (taLayoutDet.dividedColName === "RIGHT") {
      $(".panel-item-section.left-bottom, .panel-item-section-drag-bar.panel-item-left-drag").removeClass("active");
      $(".panel-item-section.right-bottom, .panel-item-section-drag-bar.panel-item-right-drag").addClass("active");
      topPanel = taLayoutDet.currentTwoPanels.rightTop;
      bottomPanel = taLayoutDet.currentTwoPanels.rightBottom;
      taLayoutDet.currentTwoPanels.leftBottom = null;
      prevPanel = topPanel ? topPanel : taLayoutDet.currentTwoPanels.leftTop;
    }
    else {
      $(".panel-item-section.right-bottom, .panel-item-section-drag-bar.panel-item-right-drag").removeClass("active");
      $(".panel-item-section.left-bottom, .panel-item-section-drag-bar.panel-item-left-drag").addClass("active");
      taLayoutDet.currentTwoPanels.rightBottom = null;
    }

    let nextIdx = -1;
    if (!bottomPanel) {
      panelElements.every((panel, idx) => {
        if (prevPanel === panel.str) {
            nextIdx = (idx + 1) === panelElements.length ? 0 : idx + 1;
            // Now check if panel indexed with nextIdx is already open in somewhere
            if (taLayoutDet.currentTwoPanels.leftTop === panelElements[nextIdx].str || taLayoutDet.currentTwoPanels.rightTop === panelElements[nextIdx].str) {
              // If yes update the nextIdx
              nextIdx =  (nextIdx + 1) === panelElements.length ? 0 : nextIdx + 1;
            }
            if (taLayoutDet.dividedColName === "RIGHT") {
              taLayoutDet.currentTwoPanels.rightBottom = panelElements[nextIdx].str;
            }
            else {
              taLayoutDet.currentTwoPanels.leftBottom = panelElements[nextIdx].str;
            }
            return false; // Break the loop
        }
        return true;
      })
      if (nextIdx === -1) {
        taLayoutDet.currentTwoPanels = taLayoutDet.dividedColName === "LEFT" ? {
          leftTop: panelElements[0].str,
          leftBottom: panelElements[1].str,
          rightTop: panelElements[2].str,
          rightBottom: null,
        } : {
          leftTop: panelElements[0].str,
            leftBottom: null,
            rightTop: panelElements[1].str,
            rightBottom: panelElements[2].str,
        };
      }
    }

    initializeHorizontalTwoPanelDrag();
    updatePanelLayoutModes();
  } else if (+taLayoutDet.numOfPanelsEnabled === 4 && !isMobileView) {
    twoPanelCont.addClass("active");
    $(".two-panel-item.two-panel-left, .two-panel-drag-bar").addClass("active");

    $(".panel-item-section.right-bottom, .panel-item-section-drag-bar.panel-item-right-drag").addClass("active");
    $(".panel-item-section.left-bottom, .panel-item-section-drag-bar.panel-item-left-drag").addClass("active");

    initializeHorizontalTwoPanelDrag();
    updatePanelLayoutModes();
  }
  else {
    resetSinglePanelLayout();
    taLayoutDet.currentTwoPanels = {
      leftTop: null,
      leftBottom: null,
      rightTop: null,
      rightBottom: null,
    };
  }
  updatePanelOptions();
}

// Handles the DOM manipulation to update the two panel layout
function updatePanelLayoutModes () {
  // fetch the panels by their ids
  const leftTopPanel = document.getElementById(taLayoutDet.currentTwoPanels.leftTop);
  const leftBottomPanel = document.getElementById(taLayoutDet.currentTwoPanels.leftBottom);
  const rightTopPanel = document.getElementById(taLayoutDet.currentTwoPanels.rightTop);
  const rightBottomPanel = document.getElementById(taLayoutDet.currentTwoPanels.rightBottom);

  setMultiPanelModeVisiblities();
  for (const panelIdx in panelsBucket) {
    const panelCont = document.querySelector(panelsBucket[panelIdx]).childNodes;
    // Move all the panels from the left and right buckets to the main panels-container
    for (let idx = 0; idx < panelCont.length; idx++) {
      document.querySelector(".panels-container").append(panelCont[idx]);
    }
  }
  // finally append the latest panels to their respective buckets
  if (leftTopPanel) {
    document.querySelector(panelsBucket.leftTopSelector).append(leftTopPanel);
  }
  if (leftBottomPanel) {
    document.querySelector(panelsBucket.leftBottomSelector).append(leftBottomPanel);
  }
  if (rightTopPanel) {
    document.querySelector(panelsBucket.rightTopSelector).append(rightTopPanel);
  }
  if (rightBottomPanel) {
    document.querySelector(panelsBucket.rightBottomSelector).append(rightBottomPanel);
  }
  saveTaLayoutDetails();
}

// Exchanges positions of left and right panels
function exchangeTwoPanels () {
  if (+taLayoutDet.numOfPanelsEnabled === 2) {
    taLayoutDet.currentTwoPanels = {
      leftTop: taLayoutDet.currentTwoPanels.rightTop,
      rightTop: taLayoutDet.currentTwoPanels.leftTop,
    };
    updatePanelLayoutModes();
  }
  else if (+taLayoutDet.numOfPanelsEnabled === 3 || +taLayoutDet.numOfPanelsEnabled === 4) {
    taLayoutDet.currentTwoPanels = {
      leftTop: taLayoutDet.currentTwoPanels.rightTop,
      leftBottom: taLayoutDet.currentTwoPanels.rightBottom,
      rightTop: taLayoutDet.currentTwoPanels.leftTop,
      rightBottom: taLayoutDet.currentTwoPanels.leftBottom,
    };
    $(".panel-item-section.left-bottom, .panel-item-section.right-bottom, .panel-item-section-drag-bar").toggleClass("active");
    taLayoutDet.dividedColName = $(".panel-item-section.right-bottom").is(":visible") ? "RIGHT" : "LEFT";
    updatePanelOptions();
    updatePanelLayoutModes();
    initializeHorizontalTwoPanelDrag();
  }
  else {
    // taLayoutDet.numOfPanelsEnabled is 1
    alert("Exchange works only when there are two panels...");
  }
}

// Key handler / shorthand for toggling in between panels
registerKeyHandler({name: "Toggle Autograding Panel", code: "KeyA"}, function() {
  $('#autograding_results_btn button').trigger('click');
  updateCookies();
});
registerKeyHandler({name: "Toggle Rubric Panel", code: "KeyG"}, function() {
  $('#grading_rubric_btn button').trigger('click');
  updateCookies();
});
registerKeyHandler({name: "Toggle Submissions Panel", code: "KeyO"}, function() {
  $('#submission_browser_btn button').trigger('click');
  updateCookies();
});
registerKeyHandler({name: "Toggle Student Information Panel", code: "KeyS"}, function() {
  $('#student_info_btn button').trigger('click');
  updateCookies();
});
registerKeyHandler({name: "Toggle Grade Inquiry Panel", code: "KeyX"}, function() {
  $('#regrade_info_btn button').trigger('click');
  updateCookies();
});
registerKeyHandler({name: "Toggle Discussion Panel", code: "KeyD"}, function() {
  $('#discussion_browser_btn button').trigger('click');
  updateCookies();
});
registerKeyHandler({name: "Toggle Peer Panel", code: "KeyP"}, function() {
  $('#peer_info_btn button').trigger('click');
  updateCookies();
});

registerKeyHandler({name: "Toggle Notebook Panel", code: "KeyN"}, function() {
  $('#grading_rubric_btn button').trigger('click');
  updateCookies();
});
registerKeyHandler({name: "Toggle Solution/TA-Notes Panel", code: "KeyT"}, function() {
  $('#solution_ta_notes_btn button').trigger('click');
  updateCookies();
});
//-----------------------------------------------------------------------------
// Show/hide components

registerKeyHandler({name: "Open Next Component", code: 'ArrowDown'}, function(e) {
  let openComponentId = getFirstOpenComponentId();
  let numComponents = $('#component-list').find('.component-container').length;

  // Note: we use the 'toggle' functions instead of the 'open' functions
  //  Since the 'open' functions don't close any components
  if (openComponentId === NO_COMPONENT_ID) {
    // No component is open, so open the first one
    let componentId = getComponentIdByOrder(0);
    toggleComponent(componentId, true).then(function () {
      scrollToComponent(componentId);
    });
  }
  else if (openComponentId === getComponentIdByOrder(numComponents - 1)) {
    // Last component is open, close it and then open and scroll to first component
    closeComponent(openComponentId, true).then(function () {
      let componentId = getComponentIdByOrder(0);
      toggleComponent(componentId, true).then(function () {
        scrollToComponent(componentId);
      });
    });
  }
  else {
    // Any other case, open the next one
    let nextComponentId = getNextComponentId(openComponentId);
    toggleComponent(nextComponentId, true).then(function () {
      scrollToComponent(nextComponentId);
    });
  }
  e.preventDefault();
});

registerKeyHandler({name: "Open Previous Component", code: 'ArrowUp'}, function(e) {
  let openComponentId = getFirstOpenComponentId();
  let numComponents = $('#component-list').find('.component-container').length;

  // Note: we use the 'toggle' functions instead of the 'open' functions
  //  Since the 'open' functions don't close any components
  if (openComponentId === NO_COMPONENT_ID) {
    // No Component is open, so open the overall comment
    // Targets the box outside of the container, can use tab to focus comment
    //TODO: Add "Overall Comment" focusing, control
      scrollToOverallComment();
  }
  else if (openComponentId === getComponentIdByOrder(0)) {
    // First component is open, close it and then open and scroll to the last one
    closeComponent(openComponentId, true).then(function () {
      let componentId = getComponentIdByOrder(numComponents - 1);
      toggleComponent(componentId, true).then(function () {
        scrollToComponent(componentId);
      });
    });
  }
  else {
    // Any other case, open the previous one
    let prevComponentId = getPrevComponentId(openComponentId);
    toggleComponent(prevComponentId, true).then(function () {
      scrollToComponent(prevComponentId);
    });
  }
  e.preventDefault();
});

//-----------------------------------------------------------------------------
// Misc rubric options
registerKeyHandler({name: "Toggle Rubric Edit Mode", code: "KeyE"}, function() {
  let editBox = $("#edit-mode-enabled");
  editBox.prop("checked", !editBox.prop("checked"));
  onToggleEditMode();
  updateCookies();
});

//-----------------------------------------------------------------------------
// Selecting marks

registerKeyHandler({name: "Select Full/No Credit Mark", code: 'Digit0', locked: true}, function() {
  checkOpenComponentMark(0);
});
registerKeyHandler({name: "Select Mark 1", code: 'Digit1', locked: true}, function() {
  checkOpenComponentMark(1);
});
registerKeyHandler({name: "Select Mark 2", code: 'Digit2', locked: true}, function() {
  checkOpenComponentMark(2);
});
registerKeyHandler({name: "Select Mark 3", code: 'Digit3', locked: true}, function() {
  checkOpenComponentMark(3);
});
registerKeyHandler({name: "Select Mark 4", code: 'Digit4', locked: true}, function() {
  checkOpenComponentMark(4);
});
registerKeyHandler({name: "Select Mark 5", code: 'Digit5', locked: true}, function() {
  checkOpenComponentMark(5);
});
registerKeyHandler({name: "Select Mark 6", code: 'Digit6', locked: true}, function() {
  checkOpenComponentMark(6);
});
registerKeyHandler({name: "Select Mark 7", code: 'Digit7', locked: true}, function() {
  checkOpenComponentMark(7);
});
registerKeyHandler({name: "Select Mark 8", code: 'Digit8', locked: true}, function() {
  checkOpenComponentMark(8);
});
registerKeyHandler({name: "Select Mark 9", code: 'Digit9', locked: true}, function() {
  checkOpenComponentMark(9);
});

function checkOpenComponentMark(index) {
  let component_id = getFirstOpenComponentId();
  if (component_id !== NO_COMPONENT_ID) {
    let mark_id = getMarkIdFromOrder(component_id, index);
    //TODO: Custom mark id is zero as well, should use something unique
    if (mark_id === CUSTOM_MARK_ID || mark_id === 0) {
      return;
    }
    toggleCommonMark(component_id, mark_id)
      .catch(function (err) {
        console.error(err);
        alert('Error toggling mark! ' + err.message);
      });
  }
}


// expand all files in Submissions and Results section
function openAll(click_class, class_modifier) {
  $("."+click_class + class_modifier).each(function(){
    // Check that the file is not a PDF before clicking on it
    let innerText = Object.values($(this))[0].innerText;
    if (innerText.slice(-4) !== ".pdf") {
      $(this).click();
    }
  });
}
function updateValue(obj, option1, option2) {
  // Switches the value of an element between option 1 and two
  obj.text(function(i, oldText){
    if(oldText.indexOf(option1) >= 0){
      newText = oldText.replace(option1, option2);
    }
    else {
      newText = oldText.replace(option2, option1);
    }
    return newText;
  });

}
function openAutoGrading(num){
  $('#tc_' + num).click();
  if($('#testcase_' + num)[0]!=null){
    $('#testcase_' + num)[0].style.display="block";
  }
}
// expand all outputs in Auto-Grading Testcases section
function openAllAutoGrading() {
  // show all divs whose id starts with testcase_
  let clickable_divs  = $("[id^='tc_']");

  for(let i = 0; i < clickable_divs.length; i++){
    let clickable_div = clickable_divs[i];
    let num = clickable_div.id.split("_")[1];
    let content_div = $('#testcase_' + num);
    if(content_div.css("display") == "none"){
      clickable_div.click();
    }
  }
}

// close all outputs in Auto-Grading Testcases section
function closeAllAutoGrading() {
  // hide all divs whose id starts with testcase_
  $("[id^='testcase_']").hide();
  $("[id^='details_tc_']").find("span").hide();
  $("[id^='details_tc_']").find(".loading-tools-show").show();
}


function openDiv(num) {
  let elem = $('#div_viewer_' + num);
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

// delta in this function is the incremental step of points, currently hardcoded to 0.5pts
function validateInput(id, question_total, delta){
  let ele = $('#' + id);
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

function hideIfEmpty(element) {
  $(element).each(function() {
    if ($(this).hasClass("empty")) {
      $(this).hide();
    }
  });
}

function findOpenTestcases() {
  let testcase_num = [];
  let current_testcase;
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
  }
  else {
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

// Returns Non anonymized path for the submitted files by student
function getNonAnonPath(path, anon_submitter_id, user_ids){
  let nonAnonPath = "";
  let pathPieces = path.split("/");
  for (i = 1; i < pathPieces.length; i++) {
    // for non-anonymized-file-path, get the user-name from anon_submitter_id (if anonymized)
    if(i === 9){
      nonAnonPath += "/" + user_ids[anon_submitter_id];
    }
    else{
      nonAnonPath += "/" + pathPieces[i];
    }
  }
  return nonAnonPath;
}

function changeCurrentPeer(){
  let peer = $('#edit-peer-select').val();
  $('.edit-peer-components-block').hide();
  $('#edit-peer-components-form-'+peer).show();
}

function clearPeerMarks(submitter_id, gradeable_id, csrf_token){
  let peer_id = $("#edit-peer-select").val();
  let url = buildCourseUrl(['gradeable', gradeable_id, 'grading', 'clear_peer_marks']);
  $.ajax({
    url,
    data: {
      csrf_token,
      peer_id,
      submitter_id
    },
    type: "POST",
    success: function(data) {
      console.log("Successfully deleted peer marks");
      window.location.reload(true);
    },
    error: function(e) {
      console.log("Failed to delete");
    }
  });
}

function newEditPeerComponentsForm() {
  $('.popup-form').css('display', 'none');
  let form = $("#edit-peer-components-form");
  form.css("display", "block");
  captureTabInModal("edit-peer-components-form");
}

function openFrame(html_file, url_file, num, pdf_full_panel=true, panel="submission") {
  let iframe = $('#file_viewer_' + num);
  let display_file_url = buildCourseUrl(['display_file']);
  if (!iframe.hasClass('open') || iframe.hasClass('full_panel')) {
    let iframeId = "file_viewer_" + num + "_iframe";
    let directory = "";
    if (url_file.includes("submissions")) {
      directory = "submissions";
      url_file = url_file;
    }
    else if (url_file.includes("results_public")) {
      directory = "results_public";
    }
    else if (url_file.includes("results")) {
      directory = "results";
    }
    else if (url_file.includes("checkout")) {
      directory = "checkout";
    }
    // handle pdf
    if (pdf_full_panel && url_file.substring(url_file.length - 3) === "pdf") {
      viewFileFullPanel(html_file, url_file, 0, panel).then(function(){
        loadPDFToolbar();
      });
    }
    else {
      let forceFull = url_file.substring(url_file.length - 3) === "pdf" ? 500 : -1;
      let targetHeight = iframe.hasClass("full_panel") ? 1200 : 500;
      let frameHtml = `
        <iframe id="${iframeId}" onload="resizeFrame('${iframeId}', ${targetHeight}, ${forceFull});"
                src="${display_file_url}?dir=${encodeURIComponent(directory)}&file=${encodeURIComponent(html_file)}&path=${encodeURIComponent(url_file)}&ta_grading=true"
                width="95%">
        </iframe>
      `;
      iframe.html(frameHtml);
      iframe.addClass('open');
    }
  }
  if (!iframe.hasClass("full_panel") && (!pdf_full_panel || url_file.substring(url_file.length - 3) !== "pdf")) {
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
  }
  return false;
}

let fileFullPanelOptions = {
  submission: { //Main viewer (submission panel)
    viewer: "#viewer",
    fileView: "#file-view",
    gradingFileName: "#grading_file_name",
    panel: "#submission_browser",
    innerPanel: "#directory_view",
    pdfAnnotationBar: "#pdf_annotation_bar",
    saveStatus: "#save_status",
    fileContent: "#file-content",
    fullPanel: "full_panel",
    pdf: true
  },
  notebook: { //Notebook panel
    viewer: "#notebook-viewer",
    fileView: "#notebook-file-view",
    gradingFileName: "#notebook_grading_file_name",
    panel: "#notebook_view",
    innerPanel: "#notebook-main-view",
    pdfAnnotationBar: "#notebook_pdf_annotation_bar", //TODO
    saveStatus: "#notebook_save_status", //TODO
    fileContent: "#notebook-file-content",
    fullPanel: "notebook_full_panel",
    pdf: false
  }
}

function viewFileFullPanel(name, path, page_num = 0, panel="submission") {
  // debugger;
  if($(fileFullPanelOptions[panel]["viewer"]).length != 0){
    $(fileFullPanelOptions[panel]["viewer"]).remove();
  }

  let promise = loadPDF(name, path, page_num, panel);
  $(fileFullPanelOptions[panel]["fileView"]).show();
  $(fileFullPanelOptions[panel]["gradingFileName"]).html(name);
  let precision = $(fileFullPanelOptions[panel]["panel"]).width()-$(fileFullPanelOptions[panel]["innerPanel"]).width();
  let offset = $(fileFullPanelOptions[panel]["panel"]).width()-precision;
  $(fileFullPanelOptions[panel]["innerPanel"]).animate({'left': '+=' + -offset + 'px'}, 200);
  $(fileFullPanelOptions[panel]["innerPanel"]).hide();
  $(fileFullPanelOptions[panel]["fileView"]).animate({'left': '+=' + -offset + 'px'}, 200).promise();
  return promise;
}

function loadPDF(name, path, page_num, panel="submission") {
  let extension = name.split('.').pop();
  if (fileFullPanelOptions[panel]["pdf"] && extension == "pdf") {
    let gradeable_id = document.getElementById(fileFullPanelOptions[panel]["panel"].substring(1)).dataset.gradeableId;
    let anon_submitter_id = document.getElementById(fileFullPanelOptions[panel]["panel"].substring(1)).dataset.anonSubmitterId;
    $('#pdf_annotation_bar').show();
    $('#save_status').show();
    return $.ajax({
      type: 'POST',
      url: buildCourseUrl(['gradeable', gradeable_id, 'grading', 'pdf']),
      data: {
        'user_id': anon_submitter_id,
        'filename': name,
        'file_path': path,
        'page_num': page_num,
        'is_anon': true,
        'csrf_token': csrfToken
      },
      success: function(data){
        $('#file-content').append(data);
      }
    });
  }
  else {
    $(fileFullPanelOptions[panel]["saveStatus"]).hide();
    $(fileFullPanelOptions[panel]["fileContent"]).append("<div id=\"file_viewer_" + fileFullPanelOptions[panel]["fullPanel"] + "\" class=\"full_panel\" data-file_name=\"\" data-file_url=\"\"></div>");
    $("#file_viewer_" + fileFullPanelOptions[panel]["fullPanel"]).empty();
    $("#file_viewer_" + fileFullPanelOptions[panel]["fullPanel"]).attr("data-file_name", "");
    $("#file_viewer_" + fileFullPanelOptions[panel]["fullPanel"]).attr("data-file_url", "");
    $("#file_viewer_" + fileFullPanelOptions[panel]["fullPanel"]).attr("data-file_name", name);
    $("#file_viewer_" + fileFullPanelOptions[panel]["fullPanel"]).attr("data-file_url", path);
    openFrame(name, path, fileFullPanelOptions[panel]["fullPanel"], false);
    $("#file_viewer_" + fileFullPanelOptions[panel]["fullPanel"] + "_iframe").css("max-height", "1200px");
    // $("#file_viewer_" + fileFullPanelOptions[panel]["fullPanel"] + "_iframe").height("100%");
  }
}

function collapseFile(panel = "submission"){
  //Removing these two to reset the full panel viewer.
  $("#file_viewer_" + fileFullPanelOptions[panel]["fullPanel"]).remove();
  if(fileFullPanelOptions[panel]["pdf"]) {
    $("#content-wrapper").remove();
    if($("#pdf_annotation_bar").is(":visible")){
      $("#pdf_annotation_bar").hide();
    }
  }
  $(fileFullPanelOptions[panel]["innerPanel"]).show();
  var offset1 = $(fileFullPanelOptions[panel]["innerPanel"]).css('left');
  var offset2 = $(fileFullPanelOptions[panel]["innerPanel"]).width();
  $(fileFullPanelOptions[panel]["innerPanel"]).animate({'left': '-=' + offset1}, 200);
  $(fileFullPanelOptions[panel]["fileView"]).animate({'left': '+=' + offset2 + 'px'}, 200, function(){
    $(fileFullPanelOptions[panel]["fileView"]).css('left', "");
    $(fileFullPanelOptions[panel]["fileView"]).hide();
  });
}
