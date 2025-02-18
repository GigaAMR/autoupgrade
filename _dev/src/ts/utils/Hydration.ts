import { ApiResponseHydration } from '../types/apiTypes';
import { routeHandler, scriptHandler } from '../autoUpgrade';

export default class Hydration {
  /**
   * @public
   * @static
   * @type {string}
   * @description The name of the hydration event.
   */
  public static hydrationEventName: string = 'hydrate';

  /**
   * @public
   * @type {Event}
   * @description The hydration event instance.
   */
  public hydrationEvent: Event = new Event(Hydration.hydrationEventName);

  /**
   * @public
   * @param {ApiResponseHydration} data - The data containing new content and routing information.
   * @param {boolean} [fromPopState=false] - Indicates if the hydration is triggered from a popstate event.
   * @description Hydrates the specified element with new content and updates the route if necessary.
   */
  public hydrate(data: ApiResponseHydration, fromPopState?: boolean) {
    const elementToUpdate = document.getElementById(data.parent_to_update);

    if (elementToUpdate && data.new_content) {
      elementToUpdate.innerHTML = data.new_content;

      if (data.new_route) {
        scriptHandler.updateRouteScript(data.new_route);

        if (!fromPopState) {
          routeHandler.setNewRoute(data.new_route);
        }
      }

      elementToUpdate.dispatchEvent(this.hydrationEvent);
    }
  }
}
