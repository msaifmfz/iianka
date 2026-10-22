<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AttendanceRecord;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Yasumi\ProviderInterface;
use Yasumi\Yasumi;

/**
 * Builds the attendance-period spreadsheet the office pastes into payroll:
 * one row per day (令和 date, holiday flag), one column per user, each cell
 * 出 / 休 / blank. Extracted from AttendanceRecordController so the sheet
 * layout has one home and a feature test can read it back cell by cell.
 */
class AttendanceSheetExporter
{
    private const int REIWA_YEAR_OFFSET = 2018;

    private const int FIRST_USER_COLUMN = 3;

    private const int FIRST_DAY_ROW = 3;

    private const array WEEKDAYS = ['日', '月', '火', '水', '木', '金', '土'];

    /** @var array<int, ProviderInterface> */
    private array $holidayProviders = [];

    /**
     * @param  Collection<int, User>  $users
     * @param  Collection<int, AttendanceRecord>  $records
     */
    public function build(Carbon $startsOn, Carbon $endsOn, Collection $users, Collection $records): Spreadsheet
    {
        $recordsByKey = $records->keyBy(
            fn (AttendanceRecord $record): string => "{$record->user_id}-{$record->work_date->toDateString()}",
        );

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('出勤表');
        $sheet->setCellValue([1, 1], '氏名');
        $sheet->setCellValue([1, 2], '年月日');

        $users->values()->each(function (User $user, int $index) use ($sheet): void {
            $sheet->setCellValueExplicit([self::FIRST_USER_COLUMN + $index, 1], $user->name, DataType::TYPE_STRING);
        });

        $days = (int) $startsOn->diffInDays($endsOn);

        for ($offset = 0; $offset <= $days; $offset++) {
            $date = $startsOn->copy()->addDays($offset);
            $row = self::FIRST_DAY_ROW + $offset;

            $sheet->setCellValueExplicit([1, $row], $this->reiwaDateLabel($date), DataType::TYPE_STRING);
            $sheet->setCellValueExplicit([2, $row], $this->holidayLabel($date), DataType::TYPE_STRING);

            $users->values()->each(function (User $user, int $index) use ($sheet, $row, $date, $recordsByKey): void {
                $record = $recordsByKey->get("{$user->id}-{$date->toDateString()}");

                $sheet->setCellValueExplicit(
                    [self::FIRST_USER_COLUMN + $index, $row],
                    $this->attendanceLabel($record),
                    DataType::TYPE_STRING,
                );
            });
        }

        return $spreadsheet;
    }

    /**
     * e.g. 2026-09-21 → 08/9/21/(月). Year is the zero-padded 令和 year; month
     * and day stay unpadded to match the payroll sheet's existing rows.
     */
    private function reiwaDateLabel(Carbon $date): string
    {
        return sprintf(
            '%02d/%d/%d/(%s)',
            $date->year - self::REIWA_YEAR_OFFSET,
            $date->month,
            $date->day,
            self::WEEKDAYS[$date->dayOfWeek],
        );
    }

    /**
     * 元旦 is called out on its own because payroll treats it apart from the
     * other national holidays; 振替休日 and 国民の休日 count as 祝.
     */
    private function holidayLabel(Carbon $date): string
    {
        if ($date->month === 1 && $date->day === 1) {
            return '元旦';
        }

        $this->holidayProviders[$date->year] ??= Yasumi::create('Japan', $date->year);

        return $this->holidayProviders[$date->year]->isHoliday($date) ? '祝' : '';
    }

    /**
     * 早退 exports as 出 because it counts as a worked day
     * (AttendanceRecord::WORKED_DAY_STATUSES). An unmarked day stays blank
     * rather than guessing 休, so a gap in the data stays visible in the sheet.
     */
    private function attendanceLabel(?AttendanceRecord $record): string
    {
        if (! $record instanceof AttendanceRecord) {
            return '';
        }

        return $record->countsAsWorkedDay() ? '出' : '休';
    }
}
