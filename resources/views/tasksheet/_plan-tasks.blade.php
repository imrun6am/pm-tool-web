{{-- Adds one of this person's open tasks to the plan, with no AI call. Requires
     $tasks, $input (the plan's trix input id) and $plan (its current HTML). --}}
@if ($tasks->isNotEmpty())
    <div class="mt-2" x-data="{
            add(select) {
                const option = select.selectedOptions[0];
                const editor = document.querySelector(`trix-editor[input='{{ $input }}']`)?.editor;
                if (! option?.value || ! editor) return;
                const li = document.createElement('li');
                li.textContent = option.textContent.trim();
                const end = editor.getDocument().getLength() - 1;
                editor.setSelectedRange([end, end]);
                editor.insertHTML('<ul>' + li.outerHTML + '</ul>');
                option.disabled = true;
                select.value = '';
            },
         }">
        <label for="{{ $input }}-open-tasks" class="sr-only">Add an open task to the plan</label>
        <select id="{{ $input }}-open-tasks" data-native class="field-control field-sm" @change="add($event.target)">
            <option value="">Add an open task to the plan…</option>
            @foreach ($tasks as $openTask)
                <option value="{{ $openTask->id }}" @disabled(str_contains($plan, $openTask->reference().' '))>{{ $openTask->reference() }} {{ $openTask->title }}</option>
            @endforeach
        </select>
    </div>
@endif
