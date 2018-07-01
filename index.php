<?php
/* GPX Animation Exporter v1.0
Author: Keith Bronstrup
Release Date: July 1, 2018
 */

if ($_FILES) {
    function build_zip($zipFile) {
        if ($_FILES['file']['error']) {
            $message = 'ERROR: ';
            switch ($_FILES['file']['error']) {
                case 1:
                case 2:
                    $message .= 'Uploaded file too large.';
                    break;
                case 3:
                    $message .= 'File did not upload completely. This can happen if you attempt to upload the wrong type of file; please try again with a .GPX file extension.';
                    break;
                case 4:
                    $message .= 'No file was uploaded. Please select a file and try again.';
                    break;
                case 6:
                case 7:
                    $message .= 'Disk write failed. ('.$_FILES['file']['error'].')';
                    break;
                case 8:
                    $message .= 'An extension has prevented this file upload from completing.';
                    break;
                default:
                    $message .= 'An unspecified error has occurred.';
            }

            return $message;
        }

        $in = simplexml_load_file($_FILES['file']['tmp_name']);

        if (!$in) {
            return 'ERROR: File uploaded is not in proper XML format.';
        }

        if (!property_exists($in, 'trk')
            || !property_exists($in->trk, 'trkseg')
            || !count($in->trk->trkseg)) {
            return 'ERROR: No tracking data found. Is this a proper GPX file?';
        }

        $out = clone($in);
        $out->attributes()->{'creator'} = 'GPS Animation Exporter (via '.$out->attributes()->{'creator'}.')';

        unset($out->trk->trkseg);

        if (file_exists('zips')
          && is_file('zips')) {
            unlink('zips');
        }

        if (!file_exists('zips')) {
            mkdir('zips');
        }

        $frameCount = 0;
        $zip        = new ZipArchive();

        if ($zip->open($zipFile, ZipArchive::CREATE) !== true) {
            return 'ERROR: Failed to create new ZIP archive.';
        }

        foreach ($in->trk->trkseg AS $seg) {
            $newSeg = $out->trk->addChild('trkseg');

            foreach ($seg->attributes() AS $attribute => $attValue) {
                $newSeg->addAttribute($attribute, $attValue);
            }

            foreach ($seg->trkpt AS $pt) {
                $newPt = $out->trk->trkseg->addChild('trkpt');

                foreach($pt->attributes() AS $attribute => $attValue) {
                    $newPt->addAttribute($attribute, $attValue);
                }

                foreach ($pt AS $property => $value) {
                    $newPt->addChild($property, $value);
                }

                $frameXML = $out->asXML();

                if ($frameXML === false) {
                    $zip->close();

                    unlink($zipFile);

                    return 'ERROR: Invalid data on frame #'.$frameCount.'.';
                }

                $zip->addFromString('frame'.(++$frameCount).'.gpx', $frameXML);
            }
        }

        $zip->close();

        return true;
    }

    $zipFile = 'zips/'.$_FILES['file']['name'].'.zip';
    $result  = build_zip($zipFile);
    if ($result === true) {
        header($_SERVER['SERVER_PROTOCOL'].' 200 OK');
        header("Content-Type: application/zip");
        header("Content-Transfer-Encoding: Binary");
        header("Content-Length: ".filesize($zipFile));
        header("Content-Disposition: attachment; filename=\"".basename($zipFile)."\"");

        if (readfile($zipFile)) {
            unlink($zipFile);
            die();
        }

        $result = 'ERROR: Unspecified error reading output ZIP file "'.$zipFile.'". The file has been kept for review; please <a href="mailto:keith@bronstrup.com">contact me</a> with these details if you would like assistance.';
    }
} ?>
<!DOCTYPE html>
<html>
<head>
    <title>GPS Animation Exporter</title>
</head>
<body>
<?php if (is_string($result)) echo '<pre>'.$result.'</pre>'?>
<form method="post" enctype="multipart/form-data">
    <label for="file">Select GPX File to Split:</label>
    <input type="file" id="file" name="file" accept="application/gpx+xml"><br>
    <input type="submit" value="Get ZIP">
</form>
</body>
</html>