from django.urls import path
from . import views

urlpatterns = [
    path('', views.index, name='index'),
    path('get-movies-theaters-and-showtimes/', views.get_movies_theaters_and_showtimes, name='get_movies_theaters_and_showtimes'),
    path('get-movie-recommendations/', views.get_movie_recommendations, name='get_movie_recommendations'),
    path('reset/', views.reset_conversation, name='reset_conversation'),
    path('get-theaters/<int:movie_id>/', views.get_theaters, name='get_theaters'),
    path('theater-status/<int:movie_id>/', views.theater_status, name='theater_status'),
]
