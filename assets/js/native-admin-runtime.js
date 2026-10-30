/* Shared native admin runtime configuration. No data/provider bootstrap. */
(function(root){'use strict';
 root.NativeAdminRuntime={configureUploads:function(){
   if(!root.Dropzone || !root.Dropzone.options)throw Error('native_dropzone_dependency_missing');
   // Exact native exceptions; other explicit Dropzone integrations remain unchanged.
   root.Dropzone.options.newsFeedDropzone=false;
   root.Dropzone.options.salesUpload=false;
 }};
}(window));
