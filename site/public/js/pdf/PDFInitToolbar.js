const { UI } = PDFAnnotate;
let RENDER_OPTIONS = window.RENDER_OPTIONS;
let GENERAL_INFORMATION = window.GENERAL_INFORMATION;
//Toolbar stuff
(function (){
    let active_toolbar = true;
    const debounce = (fn, time, ...args) => {
        if(active_toolbar){
            fn(args);
            active_toolbar = false;
            setTimeout(function(){
                active_toolbar = true;
            }, time);
        }
    }
    function setActiveToolbarItem(option){
        let selected = $('.tool-selected');
        let clicked_button = $("a[value="+option+"]");
        if(option != selected.attr('value')){
            //There are two classes for the icons; toolbar-action and toolbar-item.
            //toolbar-action are single use buttons such as download and clear
            //toolbar-item are continuous options such as pen, text, etc.
            if(!clicked_button.hasClass('toolbar-action')){
                $(selected[0]).removeClass('tool-selected');
                clicked_button.addClass('tool-selected');
                switch($(selected[0]).attr('value')){
                    case 'pen':
                        $('#file_content').css('overflow', 'auto');
                        $('#scroll_lock_mode').removeAttr('checked');
                        UI.disablePen();
                        break;
                    case 'eraser':
                        UI.disableEraser();
                        break;
                    case 'cursor':
                        UI.disableEdit();
                        break;
                    case 'text':
                        UI.disableText();
                        break;
                }
                $('.selection_panel').hide();
            }
            switch(option){
                case 'pen':
                    currentTool = 'pen';
                    UI.enablePen();
                    break;
                case 'eraser':
                    currentTool = 'eraser';
                    UI.enableEraser();
                    break;
                case 'cursor':
                    UI.enableEdit();
                    break;
                case 'clear':
                    clearCanvas();
                    break;
                case 'save':
                    saveFile();
                    break;
                case 'zoomin':
                    debounce(zoom, 500, 'in');
                    break;
                case 'zoomout':
                    debounce(zoom, 500, 'out');
                    break;
                case 'zoomcustom':
                    debounce(zoom, 500, 'custom');
                    break;
                case 'rotate':
                    debounce(rotate, 500);
                    break;
                case 'text':
                    currentTool = 'text';
                    UI.enableText();
                    break;
            }
        } else {
            //For color and size select
            switch(option){
                case 'pen':
                    $("#pen_selection").toggle();
                    break;
                case 'text':
                    $("#text_selection").toggle();
                    break;
            }
        }
    }

    function rotate(){
        RENDER_OPTIONS.rotate += 90;
        localStorage.setItem('rotate', RENDER_OPTIONS.rotate);
        render(GENERAL_INFORMATION.gradeable_id, GENERAL_INFORMATION.user_id, RENDER_OPTIONS.grader_id, GENERAL_INFORMATION.file_name);
    }

    function zoom(option, custom_val){
        let zoom_flag = true;
        let zoom_level = RENDER_OPTIONS.scale;
        if(option == 'in'){
            zoom_level += 1;
        } else if(option == 'out'){
            zoom_level -= 1;
        } else {
            if(custom_val != null){
                zoom_level = custom_val/100;
            } else {
                zoom_flag = false;
            }
            $('#zoom_selection').toggle();
        }
        if(zoom_level > 10 || zoom_level < 0.25){
            alert("Cannot zoom more");
            return;
        }
        RENDER_OPTIONS.scale = zoom_level;
        $("a[value='zoomcustom']").text(parseInt(RENDER_OPTIONS.scale * 100) + "%");
        if(zoom_flag){
            localStorage.setItem('scale', RENDER_OPTIONS.scale);
            render(GENERAL_INFORMATION.gradeable_id, GENERAL_INFORMATION.user_id, GENERAL_INFORMATION.grader_id, GENERAL_INFORMATION.file_name);
        }
    }

    function clearCanvas(){
        if (confirm('Are you sure you want to clear annotations?')) {
            for (let i=0; i<NUM_PAGES; i++) {
                document.querySelector(`div#pageContainer${i+1} svg.annotationLayer`).innerHTML = '';
            }

            localStorage.removeItem(`${RENDER_OPTIONS.documentId}/annotations`);
        }
    }

    function saveFile(){
        let url = buildUrl({'component': 'PDF','page': 'save_pdf_annotation'});
        let annotation_layer = localStorage.getItem(`${RENDER_OPTIONS.documentId}/${GENERAL_INFORMATION.grader_id}/annotations`);
        $.ajax({
            type: 'POST',
            url: url,
            data: {
                annotation_layer,
                GENERAL_INFORMATION
            },
            success: function(data){
                $('#save_status').text("Saved");
                $('#save_status').css('color', 'black');
            }
        });
    }

    function handleToolbarClick(e){
        setActiveToolbarItem(e.target.getAttribute('value'));
    }
    document.getElementById('pdf_annotation_icons').addEventListener('click', handleToolbarClick);
})();

// Color/size selection
(function () {
    let main_color;
    function initColors(){
        let init_color = localStorage.getItem('main_color') || '#ff0000';
        document.getElementById('color_selector').style.backgroundColor = init_color;
        setColor(init_color);
    }

    function colorMenuToggle(e){
        let shouldShow = !$('#color_selector_menu').is(':visible');
        $('.selection-menu').hide();
        shouldShow && $('#color_selector_menu').toggle();
    }

    function sizeMenuToggle(){
        let shouldShow = !$('#size_selector_menu').is(':visible');
        $('.selection-menu').hide();
        shouldShow && $('#size_selector_menu').toggle();
    }

    function changeColor(e){
        setColor(e.srcElement.getAttribute('value'))
    }

    function setColor(color){
        if(main_color != color){
            main_color = color;
            localStorage.setItem('main_color', color);
            document.getElementById('color_selector').style.backgroundColor = color;
        }
    }
    document.getElementById("color_selector").addEventListener('click', colorMenuToggle);
    document.getElementById("size_selector").addEventListener('click', sizeMenuToggle);
    document.addEventListener('colorchange', changeColor);
    initColors();
})();

// Pen stuff
(function () {
    let penSize;
    let penColor;
    let scrollLock;
    function initPen() {
        let init_size = localStorage.getItem('pen/size') || 3;
        let init_color = localStorage.getItem('main_color') || '#FF0000';
        document.getElementById('pen_size_selector').value = init_size;
        document.getElementById('pen_size_value').value = init_size;
        if($('#scroll_lock_mode').is(':checked')) {
            scrollLock = true;
        }

        setPen(init_size, init_color);
    }

    function setPen(size, color) {
        let modified = false;

        if (penSize !== size) {
            modified = true;
            penSize = size;
            localStorage.setItem('pen/size', penSize);
        }
        if (penColor !== color) {
            modified = true;
            penColor = color;
        }

        if (modified && scrollLock) {
            $('#file_content').css('overflow', 'hidden');
        }

        if (modified) {
            UI.setPen(penSize, penColor);
        }
    }

    document.getElementById('pen_size_selector').addEventListener('change', function(e){
        let value = e.target.value ? e.target.value : e.srcElement.value;
        setPen(value, penColor);
    });
    document.addEventListener('colorchange', function(e){
        setPen(penSize, e.srcElement.getAttribute('value'));
    });
    initPen();
})();

// Text stuff
(function () {
    let textSize;
    let textColor;

    function initText() {
        let init_size = localStorage.getItem('text/size') || 12;
        let init_color = localStorage.getItem('main_color') || '#FF0000';
        document.getElementById('text_size_selector').value = init_size;
        setText(init_size, init_color);
    }

    function setText(size, color) {
        let modified = false;
        if (textSize !== size) {
            modified = true;
            textSize = size;
            localStorage.setItem('text/size', textSize);
        }

        if (textColor !== color) {
            modified = true;
            textColor = color;
        }

        if (modified) {
            UI.setText(textSize, textColor);
        }
    }
    document.addEventListener('colorchange', function(e){
        setText(textSize, e.srcElement.getAttribute('value'));
    });
    document.getElementById('text_size_selector').addEventListener('change', function(e) {
        let value = e.target.value ? e.target.value : e.srcElement.value;
        setText(value, textColor);
    });
    initText();
})();