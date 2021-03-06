/*! UIkit 2.13.1 | http://www.getuikit.com | (c) 2014 YOOtheme | MIT License */
(function(addon) {

    var component;

    if (jQuery && UIkit) {
        component = addon(jQuery, UIkit);
    }

    if (typeof define == "function" && define.amd) {
        define("uikit-htmleditor", ["uikit"], function(){
            return component || addon(jQuery, UIkit);
        });
    }

})(function($, UI) {

    "use strict";

    var editors = [];

    UI.component('htmleditor', {

        defaults: {
            iframe       : false,
            mode         : 'split',
            markdown     : false,
            autocomplete : true,
            height       : 500,
            maxsplitsize : 1000,
            markedOptions: { gfm: true, tables: true, breaks: true, pedantic: true, sanitize: false, smartLists: true, smartypants: false, langPrefix: 'lang-'},
            codemirror   : { mode: 'htmlmixed', lineWrapping: true, dragDrop: false, autoCloseTags: true, matchTags: true, autoCloseBrackets: true, matchBrackets: true, indentUnit: 4, indentWithTabs: false, tabSize: 4, hintOptions: {completionSingle:false} },
            toolbar      : [ 'h2', 'h3', 'h4', 'h5', 'h6', 'bold', 'italic', 'listUl', 'listOl', 'back', 'nbsp', 'hr', 'blockquote', 'link' ], // 'image', 'strike',
            lblPreview   : 'Preview',
            lblCodeview  : 'HTML',
            lblMarkedview: 'Markdown',
            labels       : {
                h2:          'Headline 2',
                h3:          'Headline 3',
                h4:          'Headline 4',
                h5:          'Headline 5',
                h6:          'Headline 6',
                fullscreen:  'Fullscreen',
                bold :       'Bold',
                italic :     'Italic',
                strike :     'Strikethrough',
                blockquote : 'Blockquote',
                link :       'Link',
                image :      'Image',
                listUl :     'Unordered List',
                listOl :     'Ordered List',
                back :       'Back',
                hr :         'Separator',
                nbsp :       'Non-breaking space'
            },
            icons:{
                h2:          '<i class="@-icon-rz-h2"></i>',
                h3:          '<i class="@-icon-rz-h3"></i>',
                h4:          '<i class="@-icon-rz-h4"></i>',
                h5:          '<i class="@-icon-rz-h5"></i>',
                h6:          '<i class="@-icon-rz-h6"></i>',
                fullscreen:  '<i class="@-icon-rz-fullscreen"></i>',
                bold :       '<i class="@-icon-rz-bold"></i>',
                italic :     '<i class="@-icon-rz-italic"></i>',
                strike :     '<i class="@-icon-rz-strikethrough"></i>',
                blockquote : '<i class="@-icon-rz-quote"></i>',
                link :       '<i class="@-icon-rz-link"></i>',
                image :      '<i class="@-icon-rz-documents"></i>',
                listUl :     '<i class="@-icon-rz-unordered-list"></i>',
                listOl :     '<i class="@-icon-rz-ordered-list"></i>',
                back :       '<i class="@-icon-rz-back"></i>',
                hr :         '<i class="@-icon-rz-hr"></i>',
                nbsp :       '<i class="@-icon-rz-space-forced"></i>'
            }
        },

        boot: function() {

            // init code
            UI.ready(function(context) {

                UI.$('textarea[data-@-htmleditor]', context).each(function() {

                    var editor = UI.$(this), obj;

                    if (!editor.data('htmleditor')) {
                        obj = UI.htmleditor(editor, UI.Utils.options(editor.attr('data-@-htmleditor')));
                    }
                });
            });
        },

        init: function() {

            var $this = this, tpl = UI.components.htmleditor.template;

            this.CodeMirror = this.options.CodeMirror || CodeMirror;
            this.buttons    = {};

            tpl = tpl.replace(/\{:lblPreview\}/g, this.options.lblPreview);
            tpl = tpl.replace(/\{:lblCodeview\}/g, this.options.lblCodeview);

            this.htmleditor = UI.$(tpl);
            this.content    = this.htmleditor.find('.@-htmleditor-content');
            this.toolbar    = this.htmleditor.find('.@-htmleditor-toolbar');
            this.preview    = this.htmleditor.find('.@-htmleditor-preview').children().eq(0);
            this.code       = this.htmleditor.find('.@-htmleditor-code');

            this.element.before(this.htmleditor).appendTo(this.code);
            this.editor = this.CodeMirror.fromTextArea(this.element[0], this.options.codemirror);
            this.editor.htmleditor = this;
            this.editor.on('change', UI.Utils.debounce(function() { $this.render(); }, 150));
            this.editor.on('change', function() { $this.editor.save(); });
            this.code.find('.CodeMirror').css('height', this.options.height);

            // iframe mode?
            if (this.options.iframe) {

                this.iframe = UI.$('<iframe class="@-htmleditor-iframe" frameborder="0" scrolling="auto" height="100" width="100%"></iframe>');
                this.preview.append(this.iframe);

                // must open and close document object to start using it!
                this.iframe[0].contentWindow.document.open();
                this.iframe[0].contentWindow.document.close();

                this.preview.container = $(this.iframe[0].contentWindow.document).find('body');

                // append custom stylesheet
                if (typeof(this.options.iframe) === 'string') {
                   this.preview.container.parent().append('<link rel="stylesheet" href="'+this.options.iframe+'">');
                }

            } else {
                this.preview.container = this.preview;
            }

            UI.$win.on('resize', UI.Utils.debounce(function() { $this.fit(); }, 200));

            var previewContainer = this.iframe ? this.preview.container:$this.preview.parent(),
                codeContent      = this.code.find('.CodeMirror-sizer'),
                codeScroll       = this.code.find('.CodeMirror-scroll').on('scroll', UI.Utils.debounce(function() {

                    if ($this.htmleditor.attr('data-mode') == 'tab') return;

                    // calc position
                    var codeHeight       = codeContent.height() - codeScroll.height(),
                        previewHeight    = previewContainer[0].scrollHeight - ($this.iframe ? $this.iframe.height() : previewContainer.height()),
                        ratio            = previewHeight / codeHeight,
                        previewPostition = codeScroll.scrollTop() * ratio;

                    // apply new scroll
                    previewContainer.scrollTop(previewPostition);

                }, 10));

            this.htmleditor.on('click', '.@-htmleditor-button-code, .@-htmleditor-button-preview', function(e) {

                e.preventDefault();

                if ($this.htmleditor.attr('data-mode') == 'tab') {

                    $this.htmleditor.find('.@-htmleditor-button-code, .@-htmleditor-button-preview').removeClass('@-active').filter(this).addClass('@-active');

                    $this.activetab = UI.$(this).hasClass('@-htmleditor-button-code') ? 'code' : 'preview';
                    $this.htmleditor.attr('data-active-tab', $this.activetab);
                    $this.editor.refresh();
                }
            });

            // toolbar actions
            this.htmleditor.on('click', 'a[data-htmleditor-button]', function() {

                if (!$this.code.is(':visible')) return;

                $this.trigger('action.' + UI.$(this).data('htmleditor-button'), [$this.editor]);
            });

            this.preview.parent().css('height', this.code.height());

            // autocomplete
            if (this.options.autocomplete && this.CodeMirror.showHint && this.CodeMirror.hint && this.CodeMirror.hint.html) {

                this.editor.on('inputRead', UI.Utils.debounce(function() {
                    var doc = $this.editor.getDoc(), POS = doc.getCursor(), mode = $this.CodeMirror.innerMode($this.editor.getMode(), $this.editor.getTokenAt(POS).state).mode.name;

                    if (mode == 'xml') { //html depends on xml

                        var cur = $this.editor.getCursor(), token = $this.editor.getTokenAt(cur);

                        if (token.string.charAt(0) == '<' || token.type == 'attribute') {
                            $this.CodeMirror.showHint($this.editor, $this.CodeMirror.hint.html, { completeSingle: false });
                        }
                    }
                }, 100));
            }

            this.debouncedRedraw = UI.Utils.debounce(function () { $this.redraw(); }, 5);

            this.on('init.uk.component', function() {
                $this.redraw();
            });

            this.element.attr('data-@-check-display', 1).on('display.uk.check', function(e) {
                if (this.htmleditor.is(":visible")) this.fit();
            }.bind(this));

            editors.push(this);
        },

        addButton: function(name, button) {
            this.buttons[name] = button;
        },

        addButtons: function(buttons) {
            $.extend(this.buttons, buttons);
        },

        replaceInPreview: function(regexp, callback) {

            var editor = this.editor, results = [], value = editor.getValue(), offset = -1;

            this.currentvalue = this.currentvalue.replace(regexp, function() {

                offset = value.indexOf(arguments[0], ++offset);

                var match  = {
                    matches: arguments,
                    from   : translateOffset(offset),
                    to     : translateOffset(offset + arguments[0].length),
                    replace: function(value) {
                        editor.replaceRange(value, match.from, match.to);
                    },
                    inRange: function(cursor) {

                        if (cursor.line === match.from.line && cursor.line === match.to.line) {
                            return cursor.ch >= match.from.ch && cursor.ch < match.to.ch;
                        }

                        return  (cursor.line === match.from.line && cursor.ch   >= match.from.ch) ||
                                (cursor.line >   match.from.line && cursor.line <  match.to.line) ||
                                (cursor.line === match.to.line   && cursor.ch   <  match.to.ch);
                    }
                };

                var result = callback(match);

                if (!result) {
                    return arguments[0];
                }

                results.push(match);
                return result;
            });

            function translateOffset(offset) {
                var result = editor.getValue().substring(0, offset).split('\n');
                return { line: result.length - 1, ch: result[result.length - 1].length }
            }

            return results;
        },

        _buildtoolbar: function() {

            if (!(this.options.toolbar && this.options.toolbar.length)) return;

            var $this = this, bar = [];

            this.toolbar.empty();

            this.options.toolbar.forEach(function(button) {
                if (!$this.buttons[button]) return;

                var title = $this.buttons[button].title ? $this.buttons[button].title : button;

                bar.push('<li class="@-htmleditor-button-cont-'+button+'"><a class="@-htmleditor-button-'+button+'" data-htmleditor-button="'+button+'" title="'+title+'" data-@-tooltip="{animation:true}">'+$this.buttons[button].label+'</a></li>');

            });

            this.toolbar.html(UI.prefix(bar.join('\n')));
        },

        fit: function() {

            var mode = this.options.mode;

            if (mode == 'split' && this.htmleditor.width() < this.options.maxsplitsize) {
                mode = 'tab';
            }

            if (mode == 'tab') {
                if (!this.activetab) {
                    this.activetab = 'code';
                    this.htmleditor.attr('data-active-tab', this.activetab);
                }

                this.htmleditor.find('.@-htmleditor-button-code, .@-htmleditor-button-preview').removeClass('@-active')
                    .filter(this.activetab == 'code' ? '.@-htmleditor-button-code' : '.@-htmleditor-button-preview')
                    .addClass('@-active');
            }

            this.editor.refresh();
            this.preview.parent().css('height', this.code.height());

            this.htmleditor.attr('data-mode', mode);
        },

        redraw: function() {
            this._buildtoolbar();
            this.render();
            this.fit();
        },

        getMode: function() {
            return this.editor.getOption('mode');
        },

        getCursorMode: function() {
            var param = { mode: 'html'};
            this.trigger('cursorMode', [param]);
            return param.mode;
        },

        render: function() {

            this.currentvalue = this.editor.getValue();

            // empty code
            if (!this.currentvalue) {

                this.element.val('');
                this.preview.container.html('');

                return;
            }

            this.trigger('render', [this]);
            this.trigger('renderLate', [this]);

            this.preview.container.html(this.currentvalue);
        },

        addShortcut: function(name, callback) {
            var map = {};
            if (!$.isArray(name)) {
                name = [name];
            }

            name.forEach(function(key) {
                map[key] = callback;
            });

            this.editor.addKeyMap(map);

            return map;
        },

        addShortcutAction: function(action, shortcuts) {
            var editor = this;
            this.addShortcut(shortcuts, function() {
                editor.element.trigger('action.' + action, [editor.editor]);
            });
        },

        replaceSelection: function(replace) {

            var text = this.editor.getSelection();

            if (!text.length) {

                var cur     = this.editor.getCursor(),
                    curLine = this.editor.getLine(cur.line),
                    start   = cur.ch,
                    end     = start;

                while (end < curLine.length && /[\w$]+/.test(curLine.charAt(end))) ++end;
                while (start && /[\w$]+/.test(curLine.charAt(start - 1))) --start;

                var curWord = start != end && curLine.slice(start, end);

                if (curWord) {
                    this.editor.setSelection({ line: cur.line, ch: start}, { line: cur.line, ch: end });
                    text = curWord;
                }
            }

            var html = replace.replace('$1', text);

            this.editor.replaceSelection(html, 'end');
            this.editor.focus();
        },

        replaceLine: function(replace) {
            var pos  = this.editor.getDoc().getCursor(),
                text = this.editor.getLine(pos.line),
                html = replace.replace('$1', text);

            this.editor.replaceRange(html , { line: pos.line, ch: 0 }, { line: pos.line, ch: text.length });
            this.editor.setCursor({ line: pos.line, ch: html.length });
            this.editor.focus();
        },

        save: function() {
            this.editor.save();
        }
    });


    UI.components.htmleditor.template = [
        '<div class="@-htmleditor @-clearfix" data-mode="split">',
            '<div class="@-htmleditor-navbar">',
                '<ul class="@-htmleditor-navbar-nav @-htmleditor-toolbar"></ul>',
                '<div class="@-htmleditor-navbar-flip">',
                    '<ul class="@-htmleditor-navbar-nav">',
                        '<li class="@-htmleditor-button-code"><a class="@-htmleditor-button-link-code-preview @-htmleditor-button-link-code" title="Markdown" data-@-tooltip="{animation:true}">{:lblCodeview}</a></li>',
                        '<li class="@-htmleditor-button-preview"><a class="@-htmleditor-button-link-code-preview @-htmleditor-button-link-preview" title="Preview" data-@-tooltip="{animation:true}"><i class="@-icon-rz-visibility-mini"></i></a></li>', // {:lblPreview}
                        '<li class="@-htmleditor-button-fullscreen"><a class="@-htmleditor-button-link-fullscreen" data-htmleditor-button="fullscreen" title="Fullscreen"  data-@-tooltip="{animation:true}"><i class="@-icon-rz-fullscreen"></i></a></li>',
                    '</ul>',
                    '<div class="uk-htmleditor-count">',
                        '<span class="count-current"></span> / <span class="count-limit"></span>',
                    '</div>',
                '</div>',
            '</div>',
            '<div class="@-htmleditor-content">',
                '<div class="@-htmleditor-code"></div>',
                '<div class="@-htmleditor-preview"><div></div></div>',
            '</div>',
        '</div>'
    ].join('');


    UI.plugin('htmleditor', 'base', {

        init: function(editor) {

            editor.addButtons({

                h2: {
                    title  : editor.options.labels.h2,
                    label  : editor.options.icons.h2
                },
                h3: {
                    title  : editor.options.labels.h3,
                    label  : editor.options.icons.h3
                },
                h4: {
                    title  : editor.options.labels.h4,
                    label  : editor.options.icons.h4
                },
                h5: {
                    title  : editor.options.labels.h5,
                    label  : editor.options.icons.h5
                },
                h6: {
                    title  : editor.options.labels.h6,
                    label  : editor.options.icons.h6
                },
                fullscreen: {
                    title  : editor.options.labels.fullscreen,
                    label  : editor.options.icons.fullscreen
                },
                bold : {
                    title  : editor.options.labels.bold,
                    label  : editor.options.icons.bold
                },
                italic : {
                    title  : editor.options.labels.italic,
                    label  : editor.options.icons.italic
                },
                strike : {
                    title  : editor.options.labels.strike,
                    label  : editor.options.icons.strike
                },
                blockquote : {
                    title  : editor.options.labels.blockquote,
                    label  : editor.options.icons.blockquote
                },
                link : {
                    title  : editor.options.labels.link,
                    label  : editor.options.icons.link
                },
                image : {
                    title  : editor.options.labels.image,
                    label  : editor.options.icons.image
                },
                listUl : {
                    title  : editor.options.labels.listUl,
                    label  : editor.options.icons.listUl
                },
                listOl : {
                    title  : editor.options.labels.listOl,
                    label  : editor.options.icons.listOl
                },
                back : {
                    title  : editor.options.labels.back,
                    label  : editor.options.icons.back
                },
                hr : {
                    title  : editor.options.labels.hr,
                    label  : editor.options.icons.hr
                },
                nbsp : {
                    title  : editor.options.labels.nbsp,
                    label  : editor.options.icons.nbsp
                }

            });

            addAction('h2', '<h2>$1</h2>');
            addAction('h3', '<h3>$1</h3>');
            addAction('h4', '<h4>$1</h4>');
            addAction('h5', '<h5>$1</h5>');
            addAction('h6', '<h6>$1</h6>');

            addAction('back', '$1<br/>');
            addAction('paragraph', '$1<br/>');
            addAction('hr', '$1 <hr />');
            addAction('nbsp', '$1&nbsp;');

            addAction('bold', '<strong>$1</strong>');
            addAction('italic', '<em>$1</em>');
            addAction('strike', '<del>$1</del>');
            addAction('blockquote', '<blockquote><p>$1</p></blockquote>', 'replaceLine');
            addAction('link', '<a href="http://">$1</a>');
            addAction('image', '<img src="http://" alt="$1">');

            var listfn = function() {
                if (editor.getCursorMode() == 'html') {

                    var cm      = editor.editor,
                        pos     = cm.getDoc().getCursor(true),
                        posend  = cm.getDoc().getCursor(false);

                    for (var i=pos.line; i<(posend.line+1);i++) {
                        cm.replaceRange('<li>'+cm.getLine(i)+'</li>', { line: i, ch: 0 }, { line: i, ch: cm.getLine(i).length });
                    }

                    cm.setCursor({ line: posend.line, ch: cm.getLine(posend.line).length });
                    cm.focus();
                }
            }

            editor.on('action.listUl', function() {
                listfn();
            });

            editor.on('action.listOl', function() {
                listfn();
            });

            editor.htmleditor.on('click', 'a[data-htmleditor-button="fullscreen"]', function() {
                editor.htmleditor.toggleClass('@-htmleditor-fullscreen');

                var wrap = editor.editor.getWrapperElement();

                if (editor.htmleditor.hasClass('@-htmleditor-fullscreen')) {

                    editor.editor.state.fullScreenRestore = {scrollTop: window.pageYOffset, scrollLeft: window.pageXOffset, width: wrap.style.width, height: wrap.style.height};
                    wrap.style.width  = '';
                    wrap.style.height = editor.content.height()+'px';
                    document.documentElement.style.overflow = 'hidden';

                } else {

                    document.documentElement.style.overflow = '';
                    var info = editor.editor.state.fullScreenRestore;
                    wrap.style.width = info.width; wrap.style.height = info.height;
                    window.scrollTo(info.scrollLeft, info.scrollTop);
                }

                setTimeout(function() {
                    editor.fit();
                    UI.$win.trigger('resize');
                }, 50);
            });

            editor.addShortcutAction('bold', ['Ctrl-B', 'Cmd-B']);

            function addAction(name, replace, mode) {
                editor.on('action.'+name, function() {
                    if (editor.getCursorMode() == 'html') {
                        editor[mode == 'replaceLine' ? 'replaceLine' : 'replaceSelection'](replace);
                    }
                });
            }
        }
    });

    UI.plugin('htmleditor', 'markdown', {

        init: function(editor) {

            var parser = editor.options.marked || marked;

            if (!parser) return;

            parser.setOptions(editor.options.markedOptions);

            if (editor.options.markdown) {
                enableMarkdown()
            }

            addAction('h2', '##$1');
            addAction('h3', '###$1');
            addAction('h4', '####$1');
            addAction('h5', '#####$1');
            addAction('h6', '######$1');

            addAction('back', '$1   \n');
            addAction('hr', '$1 \n\n---\n\n');
            addAction('nbsp', '$1 ');

            addAction('bold', '**$1**');
            addAction('italic', '*$1*');
            addAction('strike', '~~$1~~');
            addAction('blockquote', '> $1', 'replaceLine');
            addAction('link', '[$1](http://)');
            addAction('image', '![$1](http://)');

            editor.on('action.listUl', function() {

                if (editor.getCursorMode() == 'markdown') {

                    var cm      = editor.editor,
                        pos     = cm.getDoc().getCursor(true),
                        posend  = cm.getDoc().getCursor(false);

                    for (var i=pos.line; i<(posend.line+1);i++) {
                        cm.replaceRange('* '+cm.getLine(i), { line: i, ch: 0 }, { line: i, ch: cm.getLine(i).length });
                    }

                    cm.setCursor({ line: posend.line, ch: cm.getLine(posend.line).length });
                    cm.focus();
                }
            });

            editor.on('action.listOl', function() {

                if (editor.getCursorMode() == 'markdown') {

                    var cm      = editor.editor,
                        pos     = cm.getDoc().getCursor(true),
                        posend  = cm.getDoc().getCursor(false),
                        prefix  = 1;

                    if (pos.line > 0) {
                        var prevline = cm.getLine(pos.line-1), matches;

                        if(matches = prevline.match(/^(\d+)\./)) {
                            prefix = Number(matches[1])+1;
                        }
                    }

                    for (var i=pos.line; i<(posend.line+1);i++) {
                        cm.replaceRange(prefix+'. '+cm.getLine(i), { line: i, ch: 0 }, { line: i, ch: cm.getLine(i).length });
                        prefix++;
                    }

                    cm.setCursor({ line: posend.line, ch: cm.getLine(posend.line).length });
                    cm.focus();
                }
            });

            editor.on('renderLate', function() {
                if (editor.editor.options.mode == 'gfm') {
                    editor.currentvalue = parser(editor.currentvalue);
                }
            });

            editor.on('cursorMode', function(e, param) {
                if (editor.editor.options.mode == 'gfm') {
                    var pos = editor.editor.getDoc().getCursor();
                    if (!editor.editor.getTokenAt(pos).state.base.htmlState) {
                        param.mode = 'markdown';
                    }
                }
            });

            $.extend(editor, {

                enableMarkdown: function() {
                    enableMarkdown()
                    this.render();
                },
                disableMarkdown: function() {
                    this.editor.setOption('mode', 'htmlmixed');
                    this.htmleditor.find('.@-htmleditor-button-code a').html(this.options.lblCodeview);
                    this.render();
                }

            });

            // switch markdown mode on event
            editor.on({
                enableMarkdown  : function() { editor.enableMarkdown(); },
                disableMarkdown : function() { editor.disableMarkdown(); }
            });

            function enableMarkdown() {
                editor.editor.setOption('mode', 'gfm');

                editor.htmleditor.find('.@-htmleditor-button-code a').html('<i class="@-icon-rz-visibility-mini"></i>');
            }

            function addAction(name, replace, mode) {
                editor.on('action.'+name, function() {
                    if (editor.getCursorMode() == 'markdown') {
                        editor[mode == 'replaceLine' ? 'replaceLine' : 'replaceSelection'](replace);
                    }
                });
            }
        }
    });

    return UI.htmleditor;
});
