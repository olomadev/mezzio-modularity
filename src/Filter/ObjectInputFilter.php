<?php

declare(strict_types=1);

namespace Modularity\Filter;

use Laminas\InputFilter\InputFilter;
use Laminas\InputFilter\InputInterface;

use function array_merge;
use function is_array;

class ObjectInputFilter extends InputFilter
{
    protected array $objectMessages = [];

    public function add($input, $name = null)
    {
        if (is_array($input)) {
            if (! isset($input['required'])) {
                $input['required'] = false;
            }
        }
        if ($input instanceof InputInterface) {
            if ($input->isRequired() === null) {
                $input->setRequired(false);
            }
        }

        return parent::add($input, $name);
    }

    public function getMessages(): array
    {
        $messages = [];
        foreach ($this->getInvalidInput() as $name => $input) {
            $messages[$name] = $input->getMessages();
        }
        return ! empty($this->objectMessages)
            ? array_merge($messages, $this->objectMessages)
            : $messages;
    }
}
