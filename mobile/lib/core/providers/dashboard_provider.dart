import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../api/api_client.dart';
import '../api/api_endpoints.dart';
import '../models/dashboard.dart';
import '../widgets/home_screen_widget.dart';

class DashboardNotifier
    extends StateNotifier<AsyncValue<DashboardData>> {
  DashboardNotifier() : super(const AsyncLoading());

  int _trendDays = 7;
  int get trendDays => _trendDays;

  Future<void> fetchDashboard({int? trendDays}) async {
    if (trendDays != null) _trendDays = trendDays;
    state = const AsyncLoading();
    try {
      final res = await apiClient.get(
        ApiEndpoints.dashboard,
        queryParameters: {'trend_days': _trendDays},
      );
      final data = DashboardData.fromJson(res.data as Map<String, dynamic>);
      state = AsyncData(data);
      await HomeScreenWidget.syncFromDashboard(data.stats);
    } catch (e, st) {
      state = AsyncError(e, st);
    }
  }

  Future<PickList> fetchTodayPickList() async {
    final res = await apiClient.get(ApiEndpoints.todayPickList);
    return PickList.fromJson(res.data as Map<String, dynamic>);
  }

  Future<PickList> fetchPendingPickList() async {
    final res = await apiClient.get(ApiEndpoints.pendingPickList);
    return PickList.fromJson(res.data as Map<String, dynamic>);
  }

  Future<PickListCompletionInfo> completePickListItem({
    required String kind,
    required PickListItem item,
    required String photoPath,
  }) async {
    final formData = FormData.fromMap({
      'kind': kind,
      'item_key': item.itemKey,
      'item_id': item.itemId,
      'model_id': item.modelId,
      'item_name': item.itemName,
      'photo': await MultipartFile.fromFile(
        photoPath,
        filename: 'pick_${item.itemKey}.jpg',
      ),
    });
    final res = await apiClient.post(
      ApiEndpoints.pickListCompletions,
      data: formData,
      options: Options(contentType: 'multipart/form-data'),
    );
    return PickListCompletionInfo.fromJson(res.data as Map<String, dynamic>);
  }

  Future<void> uncompletePickListItem({
    required String kind,
    required String itemKey,
  }) async {
    await apiClient.delete(
      ApiEndpoints.pickListCompletions,
      data: {
        'kind': kind,
        'item_key': itemKey,
      },
    );
  }

  Future<PickListComment> postPickListComment({
    required String kind,
    required PickListItem item,
    required String body,
  }) async {
    final res = await apiClient.post(
      ApiEndpoints.pickListComments,
      data: {
        'kind': kind,
        'item_key': item.itemKey,
        'item_id': item.itemId,
        'model_id': item.modelId,
        'item_name': item.itemName,
        'body': body,
      },
    );
    return PickListComment.fromJson(res.data as Map<String, dynamic>);
  }

  Future<void> deletePickListComment(int commentId) async {
    await apiClient.delete(ApiEndpoints.pickListComment(commentId));
  }

  Future<void> markPickListCommentsRead({
    required String kind,
    required String itemKey,
  }) async {
    await apiClient.post(
      ApiEndpoints.pickListCommentsRead,
      data: {
        'kind': kind,
        'item_key': itemKey,
      },
    );
  }
}

final dashboardProvider =
    StateNotifierProvider<DashboardNotifier, AsyncValue<DashboardData>>(
  (ref) => DashboardNotifier(),
);
