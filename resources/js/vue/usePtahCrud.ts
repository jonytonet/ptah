import { onBeforeUnmount, shallowRef } from 'vue';
import type { ShallowRef } from 'vue';
import type { CrudController, CrudState } from '../core';

/** The state of a controller as a ref that follows every change. */
export function usePtahCrud(controller: CrudController): ShallowRef<CrudState> {
    const state = shallowRef(controller.getState());
    const stop = controller.subscribe(() => {
        state.value = controller.getState();
    });

    onBeforeUnmount(stop);

    return state;
}
