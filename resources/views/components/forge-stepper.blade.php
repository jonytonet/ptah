{{--
    forge-stepper — Ptah Forge
    Props:
      - steps      : array [ ['label' => '', 'description' => ''], ... ]
      - currentStep: int  (default: 1)
      - color      : primary | success | warn  (default: primary)
--}}
@props([
    'steps'       => [],
    'currentStep' => 1,
    'color'       => 'primary',
])

@php $currentStep = (int) $currentStep; @endphp

<div {{ $attributes }}>
    {{-- Mobile: vertical --}}
    <div class="flex flex-col gap-0 md:hidden">
        @foreach($steps as $index => $step)
            @php
                $stepNum     = $index + 1;
                $isCompleted = $stepNum < $currentStep;
                $isActive    = $stepNum === $currentStep;
            @endphp
            <div class="flex items-start gap-3">
                <div class="flex flex-col items-center">
                    <div class="w-8 h-8 rounded-full flex items-center justify-center flex-shrink-0 text-sm font-semibold
                        {{ $isCompleted ? 'bg-success text-white' : ($isActive ? 'bg-primary text-white' : 'ptah-c-step_circle_idle') }}">
                        @if($isCompleted)
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                        @else
                            {{ $stepNum }}
                        @endif
                    </div>
                    @if($index < count($steps) - 1)
                        <div class="w-0.5 h-8 {{ $isCompleted ? 'bg-success' : 'ptah-c-step_line' }} mt-1"></div>
                    @endif
                </div>
                <div class="pb-6">
                    <p class="text-sm font-semibold {{ $isActive ? 'text-primary' : ($isCompleted ? 'ptah-c-step_label_done' : 'ptah-c-step_label_idle') }}">
                        {{ $step['label'] }}
                    </p>
                    @if(isset($step['description']))
                        <p class="text-xs ptah-c-step_desc mt-0.5">{{ $step['description'] }}</p>
                    @endif
                </div>
            </div>
        @endforeach
    </div>

    {{-- Desktop: horizontal --}}
    <div class="hidden md:flex items-center">
        @foreach($steps as $index => $step)
            @php
                $stepNum     = $index + 1;
                $isCompleted = $stepNum < $currentStep;
                $isActive    = $stepNum === $currentStep;
            @endphp
            <div class="flex flex-col items-center flex-1">
                <div class="flex items-center w-full">
                    @if($index > 0)
                        <div class="flex-1 h-0.5 {{ $isCompleted ? 'bg-success' : 'ptah-c-step_line' }}"></div>
                    @endif
                    <div class="w-9 h-9 rounded-full flex items-center justify-center flex-shrink-0 text-sm font-semibold
                        {{ $isCompleted ? 'bg-success text-white' : ($isActive ? 'bg-primary text-white ring-4 ring-primary/20' : 'ptah-c-step_circle_idle') }}">
                        @if($isCompleted)
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                        @else
                            {{ $stepNum }}
                        @endif
                    </div>
                    @if($index < count($steps) - 1)
                        <div class="flex-1 h-0.5 {{ $isCompleted ? 'bg-success' : 'ptah-c-step_line' }}"></div>
                    @endif
                </div>
                <div class="mt-2 text-center px-1">
                    <p class="text-xs font-semibold {{ $isActive ? 'text-primary' : ($isCompleted ? 'ptah-c-step_label_done' : 'ptah-c-step_label_idle') }}">
                        {{ $step['label'] }}
                    </p>
                    @if(isset($step['description']))
                        <p class="text-xs ptah-c-step_desc mt-0.5">{{ $step['description'] }}</p>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
</div>
