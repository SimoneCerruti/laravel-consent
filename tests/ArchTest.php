<?php

declare(strict_types=1);

arch()->preset()->php();

arch()->preset()->security();

arch('it will not use dd(), ddd(), env(), or exit()')
    ->expect(['dd', 'ddd', 'env', 'exit'])
    ->each->not->toBeUsed();

arch('the package source declares strict types')
    ->expect('SimoneCerruti\Consent')
    ->toUseStrictTypes();

arch('the concrete classes are final')
    ->expect('SimoneCerruti\Consent')
    ->classes()
    ->toBeFinal();
