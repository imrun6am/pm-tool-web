<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Blade doesn't compile @js or @json inside a component tag's attributes: the
 * browser receives the literal text, Alpine can't parse the expression and the
 * button silently does nothing. Use {{ Js::from(...) }} there. Endpoint tests
 * can't catch this, so scan the views.
 */
class BladeComponentAttributesTest extends TestCase
{
    public function test_no_component_tag_uses_a_directive_in_its_attributes(): void
    {
        $offenders = [];

        foreach (File::allFiles(resource_path('views')) as $file) {
            preg_match_all('/<x-[\w.:-]+\b(?:[^"\'>]|"[^"]*"|\'[^\']*\')*>/', $file->getContents(), $tags);

            foreach ($tags[0] as $tag) {
                if (preg_match('/@(js|json)\(/', $tag)) {
                    $offenders[] = $file->getRelativePathname().': '.mb_substr(preg_replace('/\s+/', ' ', $tag), 0, 100);
                }
            }
        }

        $this->assertSame([], $offenders);
    }
}
