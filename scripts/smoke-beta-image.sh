#!/usr/bin/env bash
set -euo pipefail
: "${BETA_IMAGE:?}" "${BETA_VERSION:?}"
# Explicit entrypoint: never start the provisioner or connect to a CRM database.
docker run --rm --network none --entrypoint php -e EXPECTED_VERSION="$BETA_VERSION" "$BETA_IMAGE" -r '
$root="/app/";
define("FCPATH", $root); define("BASEPATH", $root."system/"); define("APPPATH", $root."application/");
if (trim(file_get_contents($root."VERSION")) !== getenv("EXPECTED_VERSION")) exit(1);
require $root."application/config/constants.php";
if (ARGWS_VERSION !== getenv("EXPECTED_VERSION")) exit(2);
$config=[]; require $root."application/config/migration.php";
$id=(int)str_replace(".", "", getenv("EXPECTED_VERSION"));
if ($config["migration_version"] !== $id || !is_file($root."application/migrations/".$id."_version_".$id.".php")) exit(3);
foreach (["192"=>192,"512"=>512,"maskable-512"=>512] as $name=>$size) {
 $info=getimagesize($root."assets/pwa/icon-".$name.".png");
 if (!$info || $info[0]!==$size || $info[1]!==$size || $info[2]!==IMAGETYPE_PNG) exit(4);
}
$_SERVER["REQUEST_METHOD"]="GET"; $_SERVER["SCRIPT_NAME"]="/crm/pwa.php";
ob_start(); require $root."pwa.php"; $manifest=json_decode(ob_get_clean(),true);
if (($manifest["scope"]??null)!=="/crm/" || ($manifest["start_url"]??null)!=="/crm/admin") exit(5);
echo "Beta image: version, native marker, PWA manifest and icons OK\n";
'
