<?php

namespace App\Support;

/**
 * Chuyển lỗi kỹ thuật (SQL/PDO) từ import thành thông báo tiếng Việt dễ hiểu.
 */
class ImportErrorHumanizer
{
    public static function humanize(?string $raw): string
    {
        if ($raw === null || trim($raw) === '') {
            return 'Lỗi không xác định.';
        }

        $raw = trim($raw);

        if (! self::looksLikeSqlOrPdoError($raw)) {
            return $raw;
        }

        if (preg_match("/Unknown column '([^']+)'/i", $raw, $m)) {
            return sprintf(
                'Cột dữ liệu "%s" chưa có trên hệ thống. File CSV có thể lệch so với phiên bản hiện tại — hãy tải mẫu import mới nhất hoặc liên hệ quản trị.',
                $m[1]
            );
        }

        if (preg_match('/42S22/i', $raw) || preg_match('/Column not found/i', $raw)) {
            return 'Cơ sở dữ liệu thiếu một hoặc nhiều cột cần thiết. File import có thể không khớp phiên bản hệ thống — hãy dùng mẫu CSV mới hoặc liên hệ quản trị.';
        }

        if (preg_match("/Duplicate entry '([^']+)'/i", $raw, $m)) {
            return sprintf(
                'Trùng dữ liệu duy nhất (đã tồn tại giá trị tương tự: %s). Hãy đổi slug/tên hoặc xóa bản ghi trùng trước khi import lại.',
                self::shortenForDisplay($m[1], 80)
            );
        }

        if (preg_match('/1062/i', $raw) || preg_match('/Integrity constraint violation:?\s*1062/i', $raw)) {
            return 'Dữ liệu bị trùng với bản ghi đã có (khóa duy nhất). Kiểm tra slug, tên chiến dịch hoặc các trường không được phép lặp.';
        }

        if (preg_match("/Field '([^']+)' doesn't have a default value/i", $raw, $m)) {
            return sprintf(
                'Thiếu giá trị bắt buộc cho trường "%s". Điền đầy đủ các cột bắt buộc trong file CSV.',
                $m[1]
            );
        }

        if (preg_match("/Column '([^']+)' cannot be null/i", $raw, $m)) {
            return sprintf(
                'Cột "%s" không được để trống. Vui lòng nhập giá trị hợp lệ.',
                $m[1]
            );
        }

        if (preg_match('/Data too long for column/i', $raw) && preg_match("/column '([^']+)'/i", $raw, $m)) {
            return sprintf(
                'Nội dung quá dài so với giới hạn cho cột "%s". Rút ngắn văn bản hoặc chia nhỏ dữ liệu.',
                $m[1]
            );
        }

        if (preg_match('/22001/i', $raw) || preg_match('/String data, right truncated/i', $raw)) {
            return 'Một hoặc nhiều ô văn bản vượt quá độ dài cho phép. Hãy rút ngắn nội dung trong file CSV.';
        }

        if (preg_match('/1452/i', $raw) || preg_match('/foreign key constraint fails/i', $raw)) {
            return 'Tham chiếu không hợp lệ (ví dụ: cửa hàng hoặc danh mục không tồn tại). Kiểm tra tên/ID liên kết trong file.';
        }

        if (preg_match('/23000/i', $raw) || preg_match('/Integrity constraint violation/i', $raw)) {
            return 'Dữ liệu không thỏa ràng buộc trong cơ sở dữ liệu (trùng lặp hoặc tham chiếu sai). Kiểm tra lại các dòng trong file.';
        }

        if (preg_match('/Connection refused|Connection timed out|server has gone away/i', $raw)) {
            return 'Không kết nối được tới cơ sở dữ liệu tạm thời. Thử import lại sau; nếu vẫn lỗi, liên hệ quản trị.';
        }

        return 'Đã xảy ra lỗi khi lưu dữ liệu. Kiểm tra định dạng file CSV và mẫu cột; nếu lỗi lặp lại, liên hệ quản trị (không cần gửi mã lỗi kỹ thuật).';
    }

    protected static function looksLikeSqlOrPdoError(string $raw): bool
    {
        return str_contains($raw, 'SQLSTATE[')
            || str_contains($raw, 'PDOException')
            || preg_match('/\bSQL:\s*/i', $raw) === 1
            || preg_match('/^\(SQL:/i', $raw) === 1;
    }

    protected static function shortenForDisplay(string $value, int $max): string
    {
        if (strlen($value) <= $max) {
            return $value;
        }

        return substr($value, 0, $max - 3) . '...';
    }
}
