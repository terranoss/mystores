package com.melur.mystores

import android.appwidget.AppWidgetManager
import android.content.Context
import android.content.SharedPreferences
import android.widget.RemoteViews
import es.antonborri.home_widget.HomeWidgetLaunchIntent
import es.antonborri.home_widget.HomeWidgetProvider

class MyStoresWidgetProvider : HomeWidgetProvider() {
    override fun onUpdate(
        context: Context,
        appWidgetManager: AppWidgetManager,
        appWidgetIds: IntArray,
        widgetData: SharedPreferences,
    ) {
        appWidgetIds.forEach { widgetId ->
            val views = RemoteViews(context.packageName, R.layout.mystores_widget).apply {
                setTextViewText(
                    R.id.widget_orders_today,
                    widgetData.getString("orders_today", "—") ?: "—",
                )
                setTextViewText(
                    R.id.widget_pending_orders,
                    widgetData.getString("pending_orders", "—") ?: "—",
                )
                setTextViewText(
                    R.id.widget_updated_at,
                    widgetData.getString("updated_at", "Open app to refresh")
                        ?: "Open app to refresh",
                )
                val pendingIntent = HomeWidgetLaunchIntent.getActivity(
                    context,
                    MainActivity::class.java,
                )
                setOnClickPendingIntent(R.id.widget_root, pendingIntent)
            }
            appWidgetManager.updateAppWidget(widgetId, views)
        }
    }
}
