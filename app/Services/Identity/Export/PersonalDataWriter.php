<?php

namespace App\Services\Identity\Export;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use JsonException;

/**
 * Streams sections of rows into `data.json`, 500 rows at a time. A row is the model's own
 * attributes as the person would read them: the public `uuid` as `id`, internal keys left out.
 */
class PersonalDataWriter
{
    private const BATCH = 500;

    /** @var array<string, array{title: string, rows: int}> */
    private array $sections = [];

    /** @var resource */
    private $handle;

    public function __construct(string $path, DateTimeInterface $exportedAt)
    {
        $this->handle = fopen($path, 'wb');

        fwrite($this->handle, '{"exported_at":' . $this->json($exportedAt->format('Y-m-d\TH:i:s.v\Z')) . ',"sections":{');
    }

    /**
     * Write every row of a query as one section.
     *
     * @param  Builder<Model>  $query
     * @param  list<string>  $except  attributes never written, such as secrets
     */
    public function section(string $key, string $title, Builder $query, array $except = []): void
    {
        $this->open($key, $title);

        $first = true;

        foreach ($query->lazyById(self::BATCH) as $model) {
            fwrite($this->handle, ($first ? '' : ',') . $this->json($this->row($model, $except)));

            $first = false;
            $this->sections[$key]['rows']++;
        }

        fwrite($this->handle, ']');
    }

    /**
     * Write one record that is not a table row, such as the account itself.
     *
     * @param  array<string, mixed>  $record
     */
    public function record(string $key, string $title, array $record): void
    {
        $this->open($key, $title);

        fwrite($this->handle, $this->json($record) . ']');

        $this->sections[$key]['rows'] = 1;
    }

    /**
     * Close the document and return what each section held, for the summary.
     *
     * @return array<string, array{title: string, rows: int}>
     */
    public function finish(): array
    {
        fwrite($this->handle, '}}');
        fclose($this->handle);

        return $this->sections;
    }

    private function open(string $key, string $title): void
    {
        fwrite($this->handle, ($this->sections === [] ? '' : ',') . $this->json($key) . ':[');

        $this->sections[$key] = ['title' => $title, 'rows' => 0];
    }

    /**
     * @param  list<string>  $except
     * @return array<string, mixed>
     */
    private function row(Model $model, array $except): array
    {
        $row = [];

        foreach ($model->attributesToArray() as $key => $value) {
            $internal = $key === 'id' || $key === 'deleted_at' || (Str::endsWith($key, '_id') && is_int($value));

            if ($internal || in_array($key, $except, true)) {
                continue;
            }

            $row[$key === 'uuid' ? 'id' : $key] = $value;
        }

        return $row;
    }

    /**
     * @throws JsonException
     */
    private function json(mixed $value): string
    {
        return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
