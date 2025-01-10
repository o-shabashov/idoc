<?php

namespace OVAC\IDoc\RuleConvertors;

use Illuminate\Validation\Rules\File;
use Illuminate\Validation\Rules\ImageFile;
use OVAC\IDoc\Traits\EnumRuleReflector;
use OVAC\IDoc\Traits\RuleSplitTrait;
use OVAC\IDoc\Traits\SplittedRuleValueObject;
use OVAC\IDoc\ValueObjects\ConvertedRuleValueObject;
use ReflectionClass;

class FileRuleConvertor implements RuleConvertor
{
    use EnumRuleReflector;
    use RuleSplitTrait;
    use SplittedRuleValueObject;

    public function __construct()
    {
        $this->formatDescription = 'Allowed mime types: (%s). Min: (%s). Max: (%s)';
    }

    public function convert(object|array|string $rule): ConvertedRuleValueObject
    {
        $ruleParts = $this->spitRule($rule);
        /** @var File $file */
        $file = $ruleParts
            ->where(fn ($part) => is_object($part) && ($part::class === File::class || $part::class === ImageFile::class))
            ->firstOrFail();
        $paramsFile = $this->getParamsFile($file);

        $required    = $this->isRequired($ruleParts);
        $description = $this->getDescription(
            implode('|', $paramsFile['mimeTypes']) ?: 'any',
            $paramsFile['minimumFileSize'] ?: '-',
            $paramsFile['maximumFileSize'] ?: '-'
        );
        $value = '';
        $type  = is_a($file, ImageFile::class) ? 'image' : 'file';

        $nullable = $this->isNullable($ruleParts);

        return new ConvertedRuleValueObject($type, $description, $required, $value, $nullable);
    }

    private function getParamsFile(File $file): array
    {
        $fileReflection  = new ReflectionClass($file);
        $mimeTypes       = $fileReflection->getProperty('allowedMimetypes')->getValue($file);
        $minimumFileSize = $fileReflection->getProperty('minimumFileSize')->getValue($file);
        $maximumFileSize = $fileReflection->getProperty('maximumFileSize')->getValue($file);

        return compact('mimeTypes', 'minimumFileSize', 'maximumFileSize');
    }
}
