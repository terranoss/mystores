import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../api/api_client.dart';
import '../api/api_endpoints.dart';
import '../models/store.dart';

class StoreNotifier extends StateNotifier<AsyncValue<List<ShopeeStore>>> {
  StoreNotifier() : super(const AsyncLoading());

  Future<void> fetchStores() async {
    state = const AsyncLoading();
    try {
      final res = await apiClient.get(ApiEndpoints.shopeeStores);
      final stores = (res.data['stores'] as List<dynamic>)
          .map((s) => ShopeeStore.fromJson(s as Map<String, dynamic>))
          .toList();
      state = AsyncData(stores);
    } catch (e, st) {
      state = AsyncError(e, st);
    }
  }

  Future<String?> getAuthUrl(MarketplaceType platform) async {
    try {
      final endpoint = platform == MarketplaceType.lazada
          ? ApiEndpoints.lazadaAuthUrl
          : ApiEndpoints.shopeeAuthUrl;
      final res = await apiClient.get(endpoint);
      return res.data['url'] as String?;
    } catch (_) {
      return null;
    }
  }

  Future<String?> reauthorizeStore(int id, MarketplaceType platform) async {
    try {
      final endpoint = platform == MarketplaceType.lazada
          ? ApiEndpoints.lazadaReauthorize(id)
          : ApiEndpoints.shopeeReauthorize(id);
      final res = await apiClient.post(endpoint);
      return res.data['url'] as String?;
    } catch (_) {
      return null;
    }
  }

  Future<bool> disconnectStore(int id, MarketplaceType platform) async {
    try {
      final endpoint = platform == MarketplaceType.lazada
          ? ApiEndpoints.lazadaDisconnect(id)
          : ApiEndpoints.shopeeDisconnect(id);
      await apiClient.post(endpoint);
      await fetchStores();
      return true;
    } catch (_) {
      return false;
    }
  }

  Future<bool> unlinkStore(int id, MarketplaceType platform) async {
    try {
      final endpoint = platform == MarketplaceType.lazada
          ? ApiEndpoints.lazadaUnlink(id)
          : ApiEndpoints.shopeeUnlink(id);
      await apiClient.delete(endpoint,
          data: {'confirmation': 'DELETE STORE DATA'});
      await fetchStores();
      return true;
    } catch (_) {
      return false;
    }
  }
}

final storeProvider =
    StateNotifierProvider<StoreNotifier, AsyncValue<List<ShopeeStore>>>(
  (ref) => StoreNotifier(),
);
