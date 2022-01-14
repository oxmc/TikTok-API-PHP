<?php
namespace Sovit\TikTok;

class Helper {
    public static function normalize($string) {
        $string = preg_replace("/([^a-z0-9])/", "-", strtolower($string));
        $string = preg_replace("/(\s+)/", "-", strtolower($string));
        $string = preg_replace("/([-]+){2,}/", "-", strtolower($string));
        return $string;
    }

    public static function parseData($items = []) {
        $final = [];
        foreach ($items as $item) {
            $final[] = (object) [
                "id"                => @$item->itemInfos->id,
                "desc"              => @$item->itemInfos->text,
                "createTime"        => @$item->itemInfos->createTime,
                "video"             => (object) [
                    "id"            => "awesome",
                    "height"       => @$item->itemInfos->video->videoMeta->height,
                    "width"        => @$item->itemInfos->video->videoMeta->width,
                    "duration"     => @$item->itemInfos->video->videoMeta->duration,
                    "ratio"        => @$item->itemInfos->video->videoMeta->height,
                    "cover"        => @$item->itemInfos->covers[0],
                    "originCover"  => @$item->itemInfos->coversOrigin[0],
                    "dynamicCover" => @$item->itemInfos->coversDynamic[0],
                    "playAddr"     => @$item->itemInfos->video->urls[0],
                    "downloadAddr" => @$item->itemInfos->video->urls[0],
                ],
                "author"            => (object) [
                    "id"           => @$item->authorInfos->userId,
                    "uniqueId"     => @$item->authorInfos->uniqueId,
                    "nickname"     => @$item->authorInfos->nickName,
                    "avatarThumb"  => @$item->authorInfos->covers[0],
                    "avatarMedium" => @$item->authorInfos->coversMedium[0],
                    "avatarLarger" => @$item->authorInfos->coversLarger[0],
                    "signature"    => @$item->authorInfos->signature,
                    "verified"     => @$item->authorInfos->verified,
                    "secUid"       => @$item->authorInfos->secUid,
                ],
                "music"             => (object) [
                    "id"          => @$item->musicInfos->musicId,
                    "title"       => @$item->musicInfos->musicName,
                    "playUrl"     => @$item->musicInfos->playUrl[0],
                    "coverThumb"  => @$item->musicInfos->covers[0],
                    "coverMedium" => @$item->musicInfos->coversMedium[0],
                    "coverLarge"  => @$item->musicInfos->coversLarger[0],
                    "authorName"  => @$item->musicInfos->authorName,
                    "original"    => @$item->musicInfos->original,
                ],
                "stats"             => (object) [
                    "diggCount"    => @$item->itemInfos->diggCount,
                    "shareCount"   => @$item->itemInfos->shareCount,
                    "commentCount" => @$item->itemInfos->commentCount,
                    "playCount"    => @$item->itemInfos->playCount,
                ],
                "originalItem"      => @$item->itemInfos->isOriginal,
                "officalItem"       => @$item->itemInfos->isOfficial,
                "secret"            => @$item->itemInfos->secret,
                "forFriend"         => @$item->itemInfos->forFriend,
                "digged"            => @$item->itemInfos->liked,
                "itemCommentStatus" => @$item->itemInfos->commentStatus,
                "showNotPass"       => @$item->itemInfos->showNotPass,
                "vl1"               => false,

            ];
        }
        return $final;
    }

    public static function string_between($string, $start, $end) {
        $string = ' ' . $string;
        $ini    = strpos($string, $start);
        if (0 == $ini) {
            return '';
        }

        $ini += strlen($start);
        $len = strpos($string, $end, $ini) - $ini;
        return substr($string, $ini, $len);
    }

    public static function makeId() {
        $characters = '0123456789';
        $randomString = '';
        $n = 16;
        for ($i = 0; $i < $n; $i++) {
            $index = rand(0, strlen($characters) - 1);
            $randomString .= $characters[$index];
        }

        return "68" . $randomString;
    }

    public static function setMeta(bool $isSuccess, int $http_code, int|string|null $tiktok_code): array {
        $keys = array_keys(Codes::list);
        $result = [
            'meta' => (object) [
                'success' => $isSuccess && $tiktok_code == 0,
                'http_code' => $http_code,
                'tiktok_code' => $tiktok_code,
                'tiktok_msg' => $tiktok_code ? (in_array($tiktok_code, $keys) ? Codes::list[$tiktok_code] : 'Unknown error') : null
            ]
        ];
        return $result;
    }

    /**
     * Verify Fingerprint
     * @return string
     */
    public static function verify_fp(): string {
        $chars = str_split("0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz");
        $chunks = [];
        $timeStr = base_convert(microtime(true), 10, 36);
        for ($i = 0; $i < 36; $i++) {
            if (\in_array($i, [8, 13, 18, 23])) {
                $chunks[$i] = "_";
            } elseif ($i == 14) {
                $chunks[$i] = "4";
            } else {
                $o = 0 | rand(0, count($chars) - 1);
                $chunks[$i] = $chars[19 === $i ? 3 & $o | 8 : $o];
            }
        }
        return "verify_" . $timeStr . "_" . implode("", $chunks);
    }
}
