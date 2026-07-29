<?php

declare(strict_types=1);

namespace Sejongtf\Synology\Services\Personal\Api\Profile;

use Sejongtf\Synology\Api;

/**
 * 프로필 사진.
 *
 * DSM 은 `upload` 도 광고하지만 여기서는 **일부러 뺐다.** 그건 `multipart/form-data`
 * 요청이고 이 패키지는 모든 요청을 `application/x-www-form-urlencoded` 로 보낸다 —
 * 멀티파트 전송이 아예 없다. `$methods` 에 남겨 두면 매직 호출로 불려서 깨진 요청이
 * 나가므로, 없는 편이 낫다(부르면 `BadMethodCallException`).
 *
 * @method array delete(array $params)
 * @method array get_encoded(array $params)
 */
class Photo extends Api
{
    const API_NAME = 'SYNO.Personal.Profile.Photo';

    /** @var array<string, int> */
    protected array $methods = [
        'get' => 2,
        'get_encoded' => 2,
        'delete' => 1,
    ];

    /**
     * @param  int  $userId  Synology User ID
     * @return array|\Sejongtf\Synology\Message\Response
     *
     * @throws \Sejongtf\Synology\Exceptions\RequestException
     */
    public function get(int $userId)
    {
        // 사진이 없으면 JSON 이 내려오므로 그때는 data() 를 돌려준다.
        $response = $this->raw(__FUNCTION__, $this->methods[__FUNCTION__], ['user_id' => $userId]);

        if ($response === null) {
            return null;
        }

        if (! $response->success() || $response->isJsonOrPlaintext()) {
            return $response->throw()->data();
        }

        return $response;
    }
}
