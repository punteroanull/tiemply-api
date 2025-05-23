<x-filament-panels::page>
    <div class="space-y-6">
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg border border-gray-200 dark:border-gray-700">
            <div class="p-6">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                    Welcome to Tiemply
                </h2>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                    Your comprehensive employee time management system
                </p>
            </div>
        </div>

        <x-filament-widgets::widgets
            :widgets="$this->getVisibleWidgets()"
            :columns="$this->getColumns()"
        />
    </div>
</x-filament-panels::page>