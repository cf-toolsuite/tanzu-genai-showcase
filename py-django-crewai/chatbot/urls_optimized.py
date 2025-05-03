from django.urls import path
from . import optimization_config

urlpatterns = [
    path('', optimization_config.index, name='index'),
    path('api/movies-theaters-showtimes/', optimization_config.get_movies_theaters_and_showtimes, name='get_movies_theaters_and_showtimes'),
    path('api/movie-recommendations/', optimization_config.get_movie_recommendations, name='get_movie_recommendations'),
    path('api/poll-movie-recommendations/', optimization_config.poll_movie_recommendations, name='poll_movie_recommendations'),
    path('api/poll-first-run-recommendations/', optimization_config.poll_first_run_recommendations, name='poll_first_run_recommendations'),
    path('api/theaters/<int:movie_id>/', optimization_config.get_theaters, name='get_theaters'),
    path('api/theater-status/<int:movie_id>/', optimization_config.theater_status, name='theater_status'),
    path('api/reset/', optimization_config.reset_conversation, name='reset_conversation'),
    path('api/config/', optimization_config.get_api_config, name='get_api_config'),
]
