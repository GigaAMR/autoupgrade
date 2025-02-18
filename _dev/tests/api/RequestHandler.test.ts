import baseApi from '../../src/ts/api/baseApi';
import { ApiResponse } from '../../src/ts/types/apiTypes';
import { RequestHandler } from '../../src/ts/api/RequestHandler';

jest.mock('../../src/ts/api/baseApi', () => ({
  post: jest.fn()
}));

const mockHydrate = jest.fn();

jest.mock('../../src/ts/utils/Hydration', () => {
  return jest.fn().mockImplementation(() => ({
    hydrate: mockHydrate
  }));
});

describe('RequestHandler', () => {
  let requestHandler: RequestHandler;

  beforeEach(() => {
    requestHandler = new RequestHandler();
    (baseApi.post as jest.Mock).mockClear();
    mockHydrate.mockClear();
  });

  it('should append admin_dir to FormData and call baseApi.post', async () => {
    const formData = new FormData();
    const route = 'some_route';
    (baseApi.post as jest.Mock).mockResolvedValue({ data: {} });

    await requestHandler.post(route, formData);

    expect(formData.get('dir')).toBe(window.AutoUpgradeVariables.admin_dir);
    expect(baseApi.post).toHaveBeenCalledWith('', formData, {
      params: { route },
      signal: expect.any(AbortSignal)
    });
  });

  it('should handle response with next_route and make two API calls', async () => {
    const response: ApiResponse = { next_route: 'next_route' };
    (baseApi.post as jest.Mock).mockResolvedValueOnce({ data: response });

    const formData = new FormData();
    const route = 'some_route';

    await requestHandler.post(route, formData);

    expect(baseApi.post).toHaveBeenCalledTimes(2);
    expect(baseApi.post).toHaveBeenNthCalledWith(1, '', formData, {
      params: { route },
      signal: expect.any(AbortSignal)
    });
    expect(baseApi.post).toHaveBeenNthCalledWith(2, '', formData, {
      params: { route: 'next_route' },
      signal: expect.any(AbortSignal)
    });
  });

  it('should handle hydration response', async () => {
    const response: ApiResponse = {
      hydration: true,
      new_content: 'new content',
      parent_to_update: 'parent',
      new_route: 'home_page'
    };

    (baseApi.post as jest.Mock).mockResolvedValueOnce({ data: response });

    const formData = new FormData();
    const route = 'some_route';

    await requestHandler.post(route, formData);

    expect(mockHydrate).toHaveBeenCalledTimes(1);
    expect(mockHydrate).toHaveBeenCalledWith(response, undefined);
  });

  it('should cancel the previous request when a new one is made', async () => {
    const formData = new FormData();
    const route = 'some_route';

    const abortSpy = jest.spyOn(AbortController.prototype, 'abort');

    await requestHandler.post(route, formData);
    await requestHandler.post(route, formData);

    expect(abortSpy).toHaveBeenCalledTimes(1);

    abortSpy.mockRestore();
  });
});
