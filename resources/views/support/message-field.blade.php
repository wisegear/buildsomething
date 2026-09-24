<label for="body">{{ isset($ticket) ? 'Add a reply' : 'Details' }}</label>
<textarea id="body" name="body" rows="10" maxlength="20000" data-editor data-support-editor>{{ old('body') }}</textarea>
<p id="editor-status" class="field-help" role="status">Write your message below. Basic formatting is supported.</p>
@error('body')<p class="field-error" role="alert">{{ $message }}</p>@enderror
