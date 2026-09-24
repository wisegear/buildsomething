import tinymce from 'tinymce';
import 'tinymce/models/dom';
import 'tinymce/themes/silver';
import 'tinymce/icons/default';
import 'tinymce/plugins/link';
import 'tinymce/plugins/lists';
import 'tinymce/plugins/table';
import 'tinymce/plugins/code';
import 'tinymce/plugins/fullscreen';
import 'tinymce/plugins/wordcount';
import 'tinymce/skins/ui/oxide/skin.min.css';
import contentUi from 'tinymce/skins/ui/oxide/content.min.css?inline';
import contentCss from 'tinymce/skins/content/default/content.min.css?inline';

const element = document.querySelector('[data-editor]');
const isSupport = element.hasAttribute('data-support-editor');
const status = document.querySelector('#editor-status');
tinymce.init({
    target: element,
    license_key: 'gpl',
    skin: false,
    content_css: false,
    content_style: `${contentUi}\n${contentCss}\nbody{font-family:Arial,sans-serif;font-size:16px;line-height:1.8;margin:24px;color:#253e35;background:#fffefb}`,
    height: isSupport ? 320 : 560,
    menubar: false,
    promotion: false,
    plugins: 'link lists table code fullscreen wordcount',
    toolbar: isSupport ? 'undo redo | bold italic underline | bullist numlist | link removeformat' : 'undo redo | blocks | bold italic | bullist numlist blockquote | link table | code fullscreen',
    setup(editor) {
        editor.on('init', () => { status.textContent = isSupport ? 'Please do not include passwords or other secrets.' : 'Make yourself at home. Save a draft whenever you like.'; });
    },
}).catch(() => { status.textContent = 'The visual editor could not load. You can write safe HTML below, or reload to try again.'; });
element.closest('form').addEventListener('submit', event => {
    const editor = tinymce.get(element.id);
    if (editor) {
        editor.save();
        if (!editor.getContent({ format: 'text' }).trim()) {
            event.preventDefault();
            status.textContent = isSupport ? 'Please enter a message.' : 'Add your story before saving.';
            editor.focus();
        }
    }
});
