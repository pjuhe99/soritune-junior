<?php
/**
 * QR 렌더링 래퍼 클래스
 * chillerlan/php-qrcode 라이브러리 래핑
 */

use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

class QRRenderer {
    private string $cacheDir;

    public function __construct(?string $cacheDir = null) {
        $this->cacheDir = $cacheDir ?? dirname(__DIR__) . '/../images/qr/';
        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0755, true);
        }
    }

    /**
     * QR 코드 이미지 생성 (PNG base64)
     */
    public function generateBase64(string $data, int $size = 300): string {
        $options = new QROptions([
            'outputType'     => QRCode::OUTPUT_IMAGE_PNG,
            'scale'          => max(1, intdiv($size, 25)),
            'imageBase64'    => true,
            'eccLevel'       => QRCode::ECC_M,
            'addQuietzone'   => true,
            'quietzoneSize'  => 2,
        ]);

        $qrcode = new QRCode($options);
        return $qrcode->render($data);
    }

    /**
     * QR 코드 이미지 파일 생성
     */
    public function generateFile(string $data, string $filename, int $size = 300): string {
        $options = new QROptions([
            'outputType'     => QRCode::OUTPUT_IMAGE_PNG,
            'scale'          => max(1, intdiv($size, 25)),
            'imageBase64'    => false,
            'eccLevel'       => QRCode::ECC_M,
            'addQuietzone'   => true,
            'quietzoneSize'  => 2,
        ]);

        $qrcode = new QRCode($options);
        $imageData = $qrcode->render($data);

        $filePath = $this->cacheDir . $filename;
        file_put_contents($filePath, $imageData);

        return $filePath;
    }

    /**
     * 캐시된 QR 가져오기 또는 생성
     */
    public function getOrCreate(string $data, string $cacheKey, int $size = 300): string {
        $filename = $cacheKey . '.png';
        $filePath = $this->cacheDir . $filename;

        if (file_exists($filePath)) {
            return '/images/qr/' . $filename;
        }

        $this->generateFile($data, $filename, $size);
        return '/images/qr/' . $filename;
    }

    /**
     * 캐시 삭제
     */
    public function clearCache(string $cacheKey): void {
        $filePath = $this->cacheDir . $cacheKey . '.png';
        if (file_exists($filePath)) {
            unlink($filePath);
        }
    }
}
