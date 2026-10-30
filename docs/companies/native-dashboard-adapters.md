
### Shared Tasks component checkpoint

`assets/js/native-tasks.js` contains the exact existing native definitions for modal creation/display, form submission, timer actions, timer dropdown rendering and checklist/comment layout. The legacy loader orders it before main.js. Extraction is verified against a frozen function-byte manifest, and loading definitions alone performs no request/bootstrap/mutation. This is not evidence that scoped Tasks endpoints or the whole modal lifecycle already work.

Scoped initialization still needs the real shared `requestGet`, `requestGetJSON`, `init_tags_inputs`, `init_editor`, `init_ajax_search`, `system_popup`, reminder validation, native modal callbacks, TinyMCE/Dropzone and permissions/options DTO. These must be composed from their actual sources, not stubbed. Timer discard currently uses the historical GET handler; issuer-scoped mutation must be changed to explicit POST/context/CSRF only alongside its real endpoint and tests. Do not admit Tasks routes merely because definitions now load.
